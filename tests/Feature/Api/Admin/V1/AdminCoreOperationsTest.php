<?php

declare(strict_types=1);

use App\Models\AdminAuditLog;
use App\Models\AdminCircleControl;
use App\Models\AdminDeviceControl;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\AdminUserControl;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Device;
use App\Models\EmailOtp;
use App\Models\IdentityDeviceTrust;
use App\Models\IdentityRefreshToken;
use App\Models\IdentitySession;
use App\Models\User;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Services\AdminRbacService;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createAdminOpsAdministrator(string $role = 'super-administrator'): AdminUser
{
    app(AdminRbacService::class)->syncDefaults();
    $admin = AdminUser::query()->create([
        'name' => 'Operations Admin',
        'email' => Str::uuid().'@admin.orbit.test',
        'password' => 'StrongPassword!123',
        'status' => AdminStatus::Active,
        'mfa_confirmed_at' => now(),
        'activated_at' => now(),
    ]);
    $roleModel = AdminRole::query()->where('slug', $role)->firstOrFail();
    $admin->roles()->sync([$roleModel->id]);

    return $admin;
}

function adminOpsHeaders(AdminUser $admin, bool $recentReauth = true): array
{
    app('auth')->forgetGuards();
    $token = $admin->createToken('admin-ops-test', ['admin'], now()->addHours(2));
    AdminSession::query()->create([
        'id' => (string) Str::uuid7(),
        'admin_user_id' => $admin->id,
        'access_token_id' => $token->accessToken->id,
        'last_seen_at' => now(),
        'idle_expires_at' => now()->addHour(),
        'expires_at' => now()->addHours(2),
        'reauthenticated_at' => $recentReauth ? now() : now()->subHour(),
        'mfa_verified_at' => now(),
    ]);

    return ['Authorization' => 'Bearer '.$token->plainTextToken];
}

function consumerOpsHeaders(User $user): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('consumer-ops-test')->plainTextToken];
}

function createAdminOpsCircle(User $owner, ?User $member = null): array
{
    $circle = Circle::query()->create([
        'created_by' => $owner->id,
        'name' => 'Operations Circle',
        'type' => 'standard',
    ]);
    $ownerMembership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $owner->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);
    $memberMembership = $member ? CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]) : null;

    return [$circle, $ownerMembership, $memberMembership];
}

function createAdminOpsDevice(User $user, string $suffix = 'one'): Device
{
    $device = new Device;
    $device->forceFill([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'client_device_id' => 'ops-'.$suffix.'-'.Str::uuid(),
        'name' => 'Legacy Device '.$suffix,
        'device_name' => 'Safe Device '.$suffix,
        'platform' => 'ios',
        'app_version' => '1.2.3',
        'os_version' => 'iOS 19',
        'public_identity_key' => 'PUBLIC-KEY-MUST-NOT-BE-RETURNED',
        'push_token' => 'PUSH-TOKEN-MUST-NOT-BE-RETURNED',
        'last_seen_at' => now(),
    ]);
    $device->save();

    return $device;
}

function createAdminOpsIdentitySession(User $user, Device $device): array
{
    $token = $user->createToken('identity-admin-ops', ['*'], now()->addMinutes(15));
    $session = IdentitySession::query()->create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'device_id' => $device->id,
        'access_token_id' => $token->accessToken->id,
        'refresh_family_id' => (string) Str::uuid7(),
        'status' => 'active',
        'last_seen_at' => now(),
        'access_expires_at' => now()->addMinutes(15),
        'refresh_expires_at' => now()->addDays(60),
    ]);
    $refresh = IdentityRefreshToken::query()->create([
        'id' => (string) Str::uuid7(),
        'session_id' => $session->id,
        'user_id' => $user->id,
        'device_id' => $device->id,
        'family_id' => $session->refresh_family_id,
        'token_hash' => hash('sha256', Str::random(64)),
        'status' => 'active',
        'expires_at' => now()->addDays(60),
    ]);

    return [$session, $refresh, $token->plainTextToken];
}

test('admin core operations require administrator authentication', function (): void {
    $this->getJson('/api/admin/v1/users')->assertUnauthorized();
    $this->getJson('/api/admin/v1/circles')->assertUnauthorized();
});

test('read only administrators can view users and circles but cannot enforce them', function (): void {
    $admin = createAdminOpsAdministrator('read-only');
    $user = User::factory()->create();
    [$circle] = createAdminOpsCircle($user);
    $headers = adminOpsHeaders($admin);

    $this->withHeaders($headers)->getJson('/api/admin/v1/users')->assertOk();
    $this->withHeaders($headers)->getJson('/api/admin/v1/circles')->assertOk();
    $this->withHeaders($headers)->patchJson('/api/admin/v1/users/'.$user->id.'/status', ['status' => 'suspended', 'reason' => 'test enforcement'])->assertForbidden();
    $this->withHeaders($headers)->patchJson('/api/admin/v1/circles/'.$circle->id.'/status', ['status' => 'frozen', 'reason' => 'test enforcement'])->assertForbidden();
});

test('user directory supports search and operational filters with pagination', function (): void {
    $admin = createAdminOpsAdministrator();
    $target = User::factory()->create(['name' => 'Special Orbit User', 'email' => 'special@example.test']);
    User::factory()->create();
    AdminUserControl::query()->create(['user_id' => $target->id, 'status' => 'suspended', 'risk_level' => 'high']);
    createAdminOpsDevice($target);

    $this->withHeaders(adminOpsHeaders($admin))
        ->getJson('/api/admin/v1/users?search=Special&account_status=suspended&risk_level=high&platform=ios&per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $target->id)
        ->assertJsonPath('data.items.0.risk_level', 'high')
        ->assertJsonPath('data.pagination.total', 1);
});

test('user operational detail excludes precise location push tokens keys and encrypted content', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    createAdminOpsDevice($user);
    DB::table('presence_states')->insert([
        'user_id' => $user->id, 'status' => 'online', 'latitude' => 31.5204, 'longitude' => 74.3587,
        'network_type' => 'wifi', 'reported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->withHeaders(adminOpsHeaders($admin))->getJson('/api/admin/v1/users/'.$user->id)->assertOk();
    $json = json_encode($response->json(), JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('PUSH-TOKEN-MUST-NOT-BE-RETURNED')
        ->not->toContain('PUBLIC-KEY-MUST-NOT-BE-RETURNED')
        ->not->toContain('31.5204')
        ->not->toContain('74.3587')
        ->and($response->json('data.presence_operations.has_location_sample'))->toBeTrue()
        ->and($response->json('data.subscription'))->toBeNull();
});

test('user suspension requires recent administrator reauthentication', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();

    $this->withHeaders(adminOpsHeaders($admin, false))
        ->patchJson('/api/admin/v1/users/'.$user->id.'/status', ['status' => 'suspended', 'reason' => 'security review'])
        ->assertStatus(428)
        ->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');
});

test('suspending a user revokes consumer access identity sessions and refresh tokens', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    $device = createAdminOpsDevice($user);
    [$session, $refresh, $access] = createAdminOpsIdentitySession($user, $device);

    $this->withHeaders(adminOpsHeaders($admin))->patchJson('/api/admin/v1/users/'.$user->id.'/status', [
        'status' => 'suspended', 'reason' => 'credible abuse signal',
    ])->assertOk()->assertJsonPath('data.status', 'suspended');

    expect(IdentitySession::query()->findOrFail($session->id)->status)->toBe('revoked')
        ->and(IdentityRefreshToken::query()->findOrFail($refresh->id)->status)->toBe('revoked');
    $this->withHeaders(['Authorization' => 'Bearer '.$access])->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('suspended accounts cannot use newly minted consumer tokens or verify email OTP', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create(['email' => 'suspended@example.test']);
    AdminUserControl::query()->create(['user_id' => $user->id, 'status' => 'suspended', 'risk_level' => 'normal']);

    $this->withHeaders(consumerOpsHeaders($user))->getJson('/api/v1/auth/me')
        ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_SUSPENDED');

    EmailOtp::query()->create([
        'email' => $user->email, 'code_hash' => Hash::make('123456'), 'attempts' => 0, 'expires_at' => now()->addMinutes(10),
    ]);
    $this->postJson('/api/v1/auth/email-otp/verify', ['email' => $user->email, 'otp' => '123456', 'device_name' => 'Phone'])
        ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_SUSPENDED');
});

test('expired temporary suspension no longer blocks consumer access and can be formally reactivated', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    AdminUserControl::query()->create(['user_id' => $user->id, 'status' => 'suspended', 'suspended_until' => now()->subMinute(), 'risk_level' => 'normal']);

    $this->withHeaders(consumerOpsHeaders($user))->getJson('/api/v1/auth/me')->assertOk();
    $this->withHeaders(adminOpsHeaders($admin))->patchJson('/api/admin/v1/users/'.$user->id.'/status', [
        'status' => 'active', 'reason' => 'temporary suspension elapsed',
    ])->assertOk()->assertJsonPath('data.status', 'active');
});

test('user feature restrictions are enforced on the consumer feature without disabling unrelated profile access', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    [$circle] = createAdminOpsCircle($user);
    $this->withHeaders(adminOpsHeaders($admin))->putJson('/api/admin/v1/users/'.$user->id.'/controls', [
        'feature_restrictions' => ['messaging'], 'rate_limit_per_minute' => null,
        'require_reverification' => false, 'risk_level' => 'watch', 'warning' => null,
        'escalate_trust_safety' => false, 'reason' => 'message spam review',
    ])->assertOk();
    $headers = consumerOpsHeaders($user);

    $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk();
    $this->withHeaders($headers)->postJson('/api/v1/circles/'.$circle->id.'/messages', [])->assertForbidden()->assertJsonPath('code', 'FEATURE_RESTRICTED');
});

test('per user administrative rate limits are enforced by consumer middleware', function (): void {
    RateLimiter::clear('admin-user-rate:1');
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    AdminUserControl::query()->create(['user_id' => $user->id, 'status' => 'active', 'risk_level' => 'normal', 'rate_limit_per_minute' => 1]);
    RateLimiter::clear('admin-user-rate:'.$user->id);
    $headers = consumerOpsHeaders($user);

    $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk();
    $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertStatus(429)->assertJsonPath('code', 'ADMIN_RATE_LIMITED');
});

test('administrative user rate limits never throttle SOS routes', function (): void {
    $user = User::factory()->create();
    AdminUserControl::query()->create(['user_id' => $user->id, 'status' => 'active', 'risk_level' => 'normal', 'rate_limit_per_minute' => 1]);
    RateLimiter::clear('admin-user-rate:'.$user->id);
    $headers = consumerOpsHeaders($user);
    $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk();

    $response = $this->withHeaders($headers)->getJson('/api/v1/sos/'.Str::uuid());
    expect($response->status())->not->toBe(429);
});

test('requiring user reverification revokes sessions and a successful email OTP clears the gate', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create(['email' => 'reverify@example.test']);
    $existing = consumerOpsHeaders($user);

    $this->withHeaders(adminOpsHeaders($admin))->putJson('/api/admin/v1/users/'.$user->id.'/controls', [
        'feature_restrictions' => [], 'rate_limit_per_minute' => null, 'require_reverification' => true,
        'risk_level' => 'normal', 'warning' => null, 'escalate_trust_safety' => false, 'reason' => 'security confirmation',
    ])->assertOk();
    $this->withHeaders($existing)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withHeaders(consumerOpsHeaders($user))->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'REVERIFICATION_REQUIRED');

    EmailOtp::query()->create([
        'email' => $user->email, 'code_hash' => Hash::make('123456'), 'attempts' => 0, 'expires_at' => now()->addMinutes(10),
    ]);
    $this->postJson('/api/v1/auth/email-otp/verify', ['email' => $user->email, 'otp' => '123456', 'device_name' => 'Verified Phone'])->assertOk();
    expect(AdminUserControl::query()->findOrFail($user->id)->require_reverification)->toBeFalse();
});

test('user controls persist warning risk classification and trust safety escalation', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    $this->withHeaders(adminOpsHeaders($admin))->putJson('/api/admin/v1/users/'.$user->id.'/controls', [
        'feature_restrictions' => ['ping'], 'rate_limit_per_minute' => 20, 'require_reverification' => false,
        'risk_level' => 'high', 'warning' => 'Abuse watch', 'escalate_trust_safety' => true, 'reason' => 'multiple abuse signals',
    ])->assertOk()->assertJsonPath('data.risk_level', 'high')->assertJsonPath('data.warning', 'Abuse watch');
    expect(AdminUserControl::query()->findOrFail($user->id)->trust_safety_escalated_at)->not->toBeNull();
});

test('device directory exposes safe metadata only', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    $device = createAdminOpsDevice($user);

    $response = $this->withHeaders(adminOpsHeaders($admin))->getJson('/api/admin/v1/users/'.$user->id.'/devices')->assertOk()->assertJsonPath('data.0.id', $device->id);
    $json = json_encode($response->json(), JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('PUSH-TOKEN-MUST-NOT-BE-RETURNED')->not->toContain('PUBLIC-KEY-MUST-NOT-BE-RETURNED');
});

test('device operations are scoped to the user in the route', function (): void {
    $admin = createAdminOpsAdministrator();
    $one = User::factory()->create();
    $two = User::factory()->create();
    $device = createAdminOpsDevice($one);

    $this->withHeaders(adminOpsHeaders($admin))->deleteJson('/api/admin/v1/users/'.$two->id.'/devices/'.$device->id, ['reason' => 'wrong owner test'])
        ->assertNotFound()->assertJsonPath('code', 'ADMIN_USER_DEVICE_NOT_FOUND');
    expect($device->fresh()->revoked_at)->toBeNull();
});

test('revoking a device clears push routing trust and device bound sessions', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    $device = createAdminOpsDevice($user);
    IdentityDeviceTrust::query()->create([
        'id' => (string) Str::uuid7(), 'user_id' => $user->id, 'device_id' => $device->id,
        'status' => 'trusted', 'requested_at' => now(), 'decided_at' => now(),
    ]);
    [$session] = createAdminOpsIdentitySession($user, $device);

    $this->withHeaders(adminOpsHeaders($admin))->deleteJson('/api/admin/v1/users/'.$user->id.'/devices/'.$device->id, ['reason' => 'lost device'])->assertOk();
    expect($device->fresh()->push_token)->toBeNull()
        ->and($device->fresh()->revoked_at)->not->toBeNull()
        ->and(IdentitySession::query()->findOrFail($session->id)->status)->toBe('revoked')
        ->and(IdentityDeviceTrust::query()->where('device_id', $device->id)->value('status'))->toBe('revoked')
        ->and(AdminDeviceControl::query()->findOrFail($device->id)->enforcement_revoked)->toBeTrue();

    $consumerHeaders = consumerOpsHeaders($user);
    $this->withHeaders($consumerHeaders)->postJson('/api/v1/devices', [
        'client_device_id' => $device->client_device_id,
        'platform' => 'ios',
        'name' => 'Attempted Re-registration',
        'push_token' => 'replacement-token',
    ])->assertForbidden()->assertJsonPath('code', 'DEVICE_ADMIN_REVOKED');

    $this->withHeaders($consumerHeaders)->postJson('/api/v1/identity/sessions', [
        'device_id' => $device->id,
    ])->assertForbidden()->assertJsonPath('code', 'DEVICE_ADMIN_REVOKED');
});

test('forcing device token rotation invalidates access while preserving the refresh family', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    $device = createAdminOpsDevice($user);
    [$session, $refresh] = createAdminOpsIdentitySession($user, $device);

    $this->withHeaders(adminOpsHeaders($admin))->postJson('/api/admin/v1/users/'.$user->id.'/devices/'.$device->id.'/rotate-token', ['reason' => 'security rotation'])
        ->assertOk()->assertJsonPath('data.rotated', 1);
    $session->refresh();
    expect($session->status)->toBe('active')->and($session->access_token_id)->toBeNull()
        ->and(IdentityRefreshToken::query()->findOrFail($refresh->id)->status)->toBe('active');
});

test('device suspicious and verification controls are persisted and audited', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    $device = createAdminOpsDevice($user);
    IdentityDeviceTrust::query()->create([
        'id' => (string) Str::uuid7(), 'user_id' => $user->id, 'device_id' => $device->id,
        'status' => 'trusted', 'requested_at' => now(), 'decided_at' => now(),
    ]);
    createAdminOpsIdentitySession($user, $device);

    $this->withHeaders(adminOpsHeaders($admin))->putJson('/api/admin/v1/users/'.$user->id.'/devices/'.$device->id.'/controls', [
        'suspicious' => true, 'require_verification' => true, 'reason' => 'unusual session pattern',
    ])->assertOk()->assertJsonPath('data.suspicious', true)->assertJsonPath('data.require_verification', true);
    expect(AdminDeviceControl::query()->findOrFail($device->id)->suspicious)->toBeTrue()
        ->and(IdentityDeviceTrust::query()->where('device_id', $device->id)->value('status'))->toBe('pending');
});

test('consumer session listing and revocation stay scoped to the selected user', function (): void {
    $admin = createAdminOpsAdministrator();
    $one = User::factory()->create();
    $two = User::factory()->create();
    $device = createAdminOpsDevice($one);
    [$session] = createAdminOpsIdentitySession($one, $device);
    $headers = adminOpsHeaders($admin);

    $this->withHeaders($headers)->getJson('/api/admin/v1/users/'.$one->id.'/sessions')->assertOk()->assertJsonPath('data.0.id', $session->id);
    $this->withHeaders($headers)->deleteJson('/api/admin/v1/users/'.$two->id.'/sessions/'.$session->id, ['reason' => 'wrong user'])->assertNotFound();
    $this->withHeaders($headers)->deleteJson('/api/admin/v1/users/'.$one->id.'/sessions/'.$session->id, ['reason' => 'support logout'])->assertOk();
    expect($session->fresh()->status)->toBe('revoked');
});

test('force logout all user sessions revokes consumer credentials', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    createAdminOpsIdentitySession($user, createAdminOpsDevice($user, 'a'));
    createAdminOpsIdentitySession($user, createAdminOpsDevice($user, 'b'));

    $this->withHeaders(adminOpsHeaders($admin))->postJson('/api/admin/v1/users/'.$user->id.'/sessions/revoke-all', ['reason' => 'account assistance'])
        ->assertOk()->assertJsonPath('data.identity_sessions', 2);
    expect(IdentitySession::query()->where('user_id', $user->id)->where('status', 'active')->count())->toBe(0);
});

test('internal user notes and tags are scoped and auditable', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    $headers = adminOpsHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/users/'.$user->id.'/notes', ['note' => 'Customer contacted support.'])->assertCreated();
    $tag = $this->withHeaders($headers)->postJson('/api/admin/v1/users/'.$user->id.'/tags', ['tag' => 'VIP Support'])->assertCreated()->json('data.id');
    $this->withHeaders($headers)->deleteJson('/api/admin/v1/users/'.$user->id.'/tags/'.$tag)->assertOk();
    expect(AdminAuditLog::query()->where('target_type', 'user')->where('target_id', (string) $user->id)->exists())->toBeTrue();
});

test('circle directory and detail expose safe operational metadata', function (): void {
    $admin = createAdminOpsAdministrator();
    $owner = User::factory()->create(['name' => 'Circle Owner']);
    $member = User::factory()->create();
    [$circle] = createAdminOpsCircle($owner, $member);
    $headers = adminOpsHeaders($admin);

    $this->withHeaders($headers)->getJson('/api/admin/v1/circles?search=Operations')->assertOk()->assertJsonPath('data.items.0.id', $circle->id);
    $response = $this->withHeaders($headers)->getJson('/api/admin/v1/circles/'.$circle->id)->assertOk()->assertJsonCount(2, 'data.members');
    $json = json_encode($response->json(), JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('ciphertext')->not->toContain('encrypted_key')->not->toContain('latitude')->not->toContain('longitude');
});

test('freezing a circle blocks consumer mutations while preserving read access', function (): void {
    $admin = createAdminOpsAdministrator();
    $owner = User::factory()->create();
    [$circle] = createAdminOpsCircle($owner);
    $this->withHeaders(adminOpsHeaders($admin))->patchJson('/api/admin/v1/circles/'.$circle->id.'/status', ['status' => 'frozen', 'reason' => 'active investigation'])->assertOk();
    $headers = consumerOpsHeaders($owner);

    $this->withHeaders($headers)->getJson('/api/v1/circles/'.$circle->id)->assertOk();
    $this->withHeaders($headers)->patchJson('/api/v1/circles/'.$circle->id, ['name' => 'Blocked Rename'])->assertStatus(423)->assertJsonPath('code', 'CIRCLE_FROZEN');
});

test('administrators can archive and restore circles without bypassing consumer ownership rules', function (): void {
    $admin = createAdminOpsAdministrator();
    $owner = User::factory()->create();
    [$circle] = createAdminOpsCircle($owner);
    $headers = adminOpsHeaders($admin);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/circles/'.$circle->id.'/status', ['status' => 'archived', 'reason' => 'operational archive'])->assertOk();
    expect($circle->fresh()->archived_at)->not->toBeNull();
    $this->withHeaders($headers)->patchJson('/api/admin/v1/circles/'.$circle->id.'/status', ['status' => 'restored', 'reason' => 'review complete'])->assertOk();
    expect($circle->fresh()->archived_at)->toBeNull();
});

test('removed circles are contained across consumer routes without interrupting SOS artifacts', function (): void {
    $admin = createAdminOpsAdministrator();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createAdminOpsCircle($owner, $member);
    $device = createAdminOpsDevice($owner, 'removed-circle');
    $now = now();

    DB::table('circle_invites')->insert([
        'id' => (string) Str::uuid7(), 'circle_id' => $circle->id, 'created_by' => $owner->id,
        'code_hash' => hash('sha256', 'code'), 'max_uses' => 1, 'uses_count' => 0,
        'expires_at' => $now->copy()->addHour(), 'created_at' => $now, 'updated_at' => $now,
    ]);

    $pingId = (string) Str::uuid7();
    DB::table('pings')->insert([
        'id' => $pingId, 'circle_id' => $circle->id,
        'sender_membership_id' => $ownerMembership->id, 'recipient_membership_id' => $memberMembership->id,
        'status' => 'pending', 'expires_at' => $now->copy()->addMinutes(2), 'created_at' => $now, 'updated_at' => $now,
    ]);

    $messageId = (string) Str::uuid7();
    DB::table('messages')->insert([
        'id' => $messageId, 'circle_id' => $circle->id, 'sender_user_id' => $owner->id,
        'sender_device_id' => $device->id, 'type' => 'text', 'expires_at' => $now->copy()->addDay(),
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $activityId = (string) Str::uuid7();
    DB::table('activity_events')->insert([
        'id' => $activityId, 'circle_id' => $circle->id, 'actor_user_id' => $owner->id,
        'event_type' => 'member.joined', 'source_type' => 'test', 'source_id' => 'removed-circle-test',
        'event_key' => 'removed-circle:'.$activityId, 'payload' => json_encode(['safe' => true], JSON_THROW_ON_ERROR),
        'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $sosActivityId = (string) Str::uuid7();
    DB::table('activity_events')->insert([
        'id' => $sosActivityId, 'circle_id' => $circle->id, 'actor_user_id' => $owner->id,
        'event_type' => 'alert.sos_resolved', 'source_type' => 'test', 'source_id' => 'sos-history',
        'event_key' => 'removed-circle-sos:'.$sosActivityId, 'payload' => json_encode(['sos_id' => 'history'], JSON_THROW_ON_ERROR),
        'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $normalNotificationId = (string) Str::uuid7();
    $sosNotificationId = (string) Str::uuid7();
    foreach ([
        [$normalNotificationId, 'message.received', 'normal'],
        [$sosNotificationId, 'sos.activated', 'highest'],
    ] as [$notificationId, $kind, $priority]) {
        DB::table('orbit_notifications')->insert([
            'id' => $notificationId, 'user_id' => $member->id, 'circle_id' => $circle->id,
            'kind' => $kind, 'priority' => $priority, 'idempotency_key' => 'removed-circle:'.$notificationId,
            'summary' => 'Operational test', 'payload' => json_encode(['safe' => true], JSON_THROW_ON_ERROR),
            'in_app_visible' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('notification_deliveries')->insert([
            'id' => (string) Str::uuid7(), 'notification_id' => $notificationId, 'target_user_id' => $member->id,
            'device_id' => 'test-device-'.$notificationId, 'channel' => 'push', 'provider' => 'fcm',
            'priority' => $priority, 'silent' => false, 'payload' => json_encode(['safe' => true], JSON_THROW_ON_ERROR),
            'status' => 'pending_provider', 'available_at' => $now, 'attempts' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $assetId = (string) Str::uuid7();
    DB::table('media_assets')->insert([
        'id' => $assetId, 'circle_id' => $circle->id, 'uploader_user_id' => $owner->id,
        'uploader_device_id' => $device->id, 'kind' => 'image', 'storage_disk' => 'local',
        'storage_path' => 'removed-circle/ciphertext.bin', 'size_bytes' => 10,
        'sha256_ciphertext' => str_repeat('a', 64), 'status' => 'ready',
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $this->withHeaders(adminOpsHeaders($admin))->patchJson('/api/admin/v1/circles/'.$circle->id.'/status', [
        'status' => 'removed', 'reason' => 'malicious circle',
    ])->assertOk();

    $consumerHeaders = consumerOpsHeaders($owner);
    $this->withHeaders($consumerHeaders)->getJson('/api/v1/circles/'.$circle->id)
        ->assertNotFound()->assertJsonPath('code', 'CIRCLE_NOT_FOUND');
    $this->withHeaders($consumerHeaders)->getJson('/api/v1/media/'.$assetId)
        ->assertNotFound()->assertJsonPath('code', 'CIRCLE_NOT_FOUND');

    expect(DB::table('circle_invites')->where('circle_id', $circle->id)->whereNull('revoked_at')->count())->toBe(0)
        ->and(DB::table('pings')->where('id', $pingId)->value('status'))->toBe('expired')
        ->and(DB::table('messages')->where('id', $messageId)->exists())->toBeFalse()
        ->and(DB::table('activity_events')->where('id', $activityId)->value('removed_at'))->not->toBeNull()
        ->and(DB::table('activity_events')->where('id', $sosActivityId)->value('removed_at'))->toBeNull()
        ->and((bool) DB::table('orbit_notifications')->where('id', $normalNotificationId)->value('in_app_visible'))->toBeFalse()
        ->and((bool) DB::table('orbit_notifications')->where('id', $sosNotificationId)->value('in_app_visible'))->toBeTrue()
        ->and(DB::table('notification_deliveries')->where('notification_id', $normalNotificationId)->value('status'))->toBe('cancelled_circle_removed')
        ->and(DB::table('notification_deliveries')->where('notification_id', $sosNotificationId)->value('status'))->toBe('pending_provider');
});

test('circle removal is blocked while an SOS incident is active', function (): void {
    $admin = createAdminOpsAdministrator();
    $owner = User::factory()->create();
    [$circle] = createAdminOpsCircle($owner);
    DB::table('sos_events')->insert([
        'id' => (string) Str::uuid7(), 'user_id' => $owner->id, 'circle_id' => $circle->id,
        'status' => 'active', 'escalation_stage' => 0, 'activated_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->withHeaders(adminOpsHeaders($admin))->patchJson('/api/admin/v1/circles/'.$circle->id.'/status', [
        'status' => 'removed', 'reason' => 'malicious circle',
    ])->assertStatus(409)->assertJsonPath('code', 'ADMIN_CIRCLE_STATUS_CONFLICT');

    expect(AdminCircleControl::query()->where('circle_id', $circle->id)->where('status', 'removed')->exists())->toBeFalse()
        ->and($circle->fresh()->archived_at)->toBeNull();
});

test('circle feature restrictions are enforced before encrypted message processing', function (): void {
    $admin = createAdminOpsAdministrator();
    $owner = User::factory()->create();
    [$circle] = createAdminOpsCircle($owner);
    $this->withHeaders(adminOpsHeaders($admin))->putJson('/api/admin/v1/circles/'.$circle->id.'/controls', [
        'feature_restrictions' => ['messaging'], 'reason' => 'temporary abuse containment',
    ])->assertOk();

    $this->withHeaders(consumerOpsHeaders($owner))->postJson('/api/v1/circles/'.$circle->id.'/messages', [])
        ->assertForbidden()->assertJsonPath('code', 'CIRCLE_FEATURE_RESTRICTED');
});

test('admin member enforcement removes non owners but blocks owner removal', function (): void {
    $admin = createAdminOpsAdministrator();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createAdminOpsCircle($owner, $member);
    $headers = adminOpsHeaders($admin);

    $this->withHeaders($headers)->deleteJson('/api/admin/v1/circles/'.$circle->id.'/members/'.$memberMembership->id, ['reason' => 'enforcement action'])->assertOk();
    $this->assertDatabaseMissing('circle_members', ['id' => $memberMembership->id]);
    $this->withHeaders($headers)->deleteJson('/api/admin/v1/circles/'.$circle->id.'/members/'.$ownerMembership->id, ['reason' => 'owner removal attempt'])
        ->assertStatus(409)->assertJsonPath('code', 'ADMIN_CIRCLE_OWNER_REMOVAL_BLOCKED');
});

test('internal circle notes and tags are available without mutating consumer-visible circle data', function (): void {
    $admin = createAdminOpsAdministrator();
    $owner = User::factory()->create();
    [$circle] = createAdminOpsCircle($owner);
    $headers = adminOpsHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/circles/'.$circle->id.'/notes', ['note' => 'Internal review only.'])->assertCreated();
    $tag = $this->withHeaders($headers)->postJson('/api/admin/v1/circles/'.$circle->id.'/tags', ['tag' => 'Safety Concern'])->assertCreated()->json('data.id');
    $this->withHeaders($headers)->deleteJson('/api/admin/v1/circles/'.$circle->id.'/tags/'.$tag)->assertOk();
    expect($circle->fresh()->description)->toBeNull();
});

test('consequential core operations include reasoned admin audit records', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();
    $this->withHeaders(adminOpsHeaders($admin))->patchJson('/api/admin/v1/users/'.$user->id.'/status', [
        'status' => 'suspended', 'reason' => 'documented security investigation',
    ])->assertOk();

    $audit = AdminAuditLog::query()->where('action', 'admin.user.suspended')->where('target_id', (string) $user->id)->firstOrFail();
    expect($audit->reason)->toBe('documented security investigation')
        ->and($audit->before_state)->toBeArray()
        ->and($audit->after_state)->toBeArray()
        ->and($audit->request_id)->not->toBeNull();
});

test('admin core controls reject unsupported restriction names', function (): void {
    $admin = createAdminOpsAdministrator();
    $user = User::factory()->create();

    $this->withHeaders(adminOpsHeaders($admin))->putJson('/api/admin/v1/users/'.$user->id.'/controls', [
        'feature_restrictions' => ['decrypt_messages'], 'rate_limit_per_minute' => null,
        'require_reverification' => false, 'risk_level' => 'normal', 'warning' => null,
        'escalate_trust_safety' => false, 'reason' => 'invalid restriction test',
    ])->assertUnprocessable()->assertJsonValidationErrors(['feature_restrictions.0']);
});
