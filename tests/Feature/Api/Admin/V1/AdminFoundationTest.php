<?php

declare(strict_types=1);

use App\Models\AdminAuditLog;
use App\Models\AdminMfaChallenge;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Mail\AdminInvitationMail;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Services\AdminInvitationService;
use App\Modules\Admin\Services\AdminRbacService;
use App\Modules\Admin\Services\AdminRecoveryCodeService;
use App\Modules\Admin\Services\AdminTotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(AdminRbacService::class)->syncDefaults();
});

function createAdminFoundationUser(string $role = 'super-administrator', array $attributes = []): AdminUser
{
    $totp = app(AdminTotpService::class);
    $admin = AdminUser::query()->create(array_merge([
        'name' => 'Orbit Administrator',
        'email' => Str::uuid().'@admin.orbit.test',
        'password' => 'OrbitAdmin!1234',
        'status' => AdminStatus::Active,
        'totp_secret' => $totp->generateSecret(),
        'mfa_confirmed_at' => now(),
        'activated_at' => now(),
    ], $attributes));
    $roleModel = AdminRole::query()->where('slug', $role)->firstOrFail();
    $admin->roles()->sync([$roleModel->id]);

    return $admin->fresh();
}

function adminFoundationHeaders(AdminUser $admin, bool $recentReauth = true): array
{
    $expiresAt = now()->addHours(8);
    $token = $admin->createToken('admin-test', ['admin'], $expiresAt);
    AdminSession::query()->create([
        'admin_user_id' => $admin->id,
        'access_token_id' => $token->accessToken->getKey(),
        'ip_hash' => hash_hmac('sha256', '127.0.0.1', (string) config('app.key')),
        'user_agent_hash' => hash_hmac('sha256', 'Symfony', (string) config('app.key')),
        'last_seen_at' => now(),
        'idle_expires_at' => now()->addMinutes(15),
        'expires_at' => $expiresAt,
        'reauthenticated_at' => $recentReauth ? now() : now()->subMinutes(30),
        'mfa_verified_at' => now(),
    ]);

    return ['Authorization' => 'Bearer '.$token->plainTextToken];
}

test('consumer tokens cannot authenticate to the administrator API', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('consumer')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'ADMIN_UNAUTHENTICATED');
});

test('administrator tokens cannot authenticate to consumer Orbit APIs', function (): void {
    $admin = createAdminFoundationUser();
    $headers = adminFoundationHeaders($admin);

    $this->withHeaders($headers)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'CONSUMER_AUTHENTICATION_REQUIRED');
});

test('administrator invitation activation requires mandatory TOTP before login', function (): void {
    Mail::fake();
    $invite = app(AdminInvitationService::class)->invite(
        'new-admin@example.com',
        'New Admin',
        ['read-only'],
        null,
        null,
        'test invitation',
    );

    $accept = $this->postJson('/api/admin/v1/auth/invitations/accept', [
        'invitation_token' => $invite['rawToken'],
        'name' => 'New Admin',
        'password' => 'OrbitAdmin!1234',
        'password_confirmation' => 'OrbitAdmin!1234',
    ])->assertOk()
        ->assertJsonPath('data.admin_id', $invite['admin']->id)
        ->assertJsonPath('data.email', 'new-admin@example.com');

    $admin = AdminUser::query()->findOrFail($invite['admin']->id);
    expect($admin->status)->toBe(AdminStatus::MfaSetup)
        ->and($admin->mfa_confirmed_at)->toBeNull();

    $this->postJson('/api/admin/v1/auth/login', [
        'email' => 'new-admin@example.com',
        'password' => 'OrbitAdmin!1234',
    ])->assertForbidden()->assertJsonPath('code', 'ADMIN_ACCOUNT_NOT_ACTIVE');

    $code = app(AdminTotpService::class)->currentCode((string) $admin->totp_secret);
    $confirm = $this->postJson('/api/admin/v1/auth/mfa/setup/confirm', [
        'setup_token' => $accept->json('data.setup_token'),
        'code' => $code,
    ])->assertOk()
        ->assertJsonPath('data.activated', true)
        ->assertJsonCount((int) config('orbit_admin.recovery_code_count', 10), 'data.recovery_codes');

    expect($admin->fresh()->status)->toBe(AdminStatus::Active)
        ->and($admin->fresh()->mfa_confirmed_at)->not->toBeNull();
});

test('MFA setup attempts persist and lock after the configured failure limit', function (): void {
    Mail::fake();
    $invite = app(AdminInvitationService::class)->invite(
        'mfa-lock@example.com',
        'MFA Lock Test',
        ['read-only'],
        null,
        null,
        'test setup lockout',
    );

    $accept = $this->postJson('/api/admin/v1/auth/invitations/accept', [
        'invitation_token' => $invite['rawToken'],
        'name' => 'MFA Lock Test',
        'password' => 'OrbitAdmin!1234',
        'password_confirmation' => 'OrbitAdmin!1234',
    ])->assertOk();

    $admin = AdminUser::query()->findOrFail($invite['admin']->id);
    $current = app(AdminTotpService::class)->currentCode((string) $admin->totp_secret);
    $invalid = $current === '000000' ? '000001' : '000000';
    $limit = max(3, (int) config('orbit_admin.mfa_max_attempts', 5));

    for ($attempt = 0; $attempt < $limit; $attempt++) {
        $this->postJson('/api/admin/v1/auth/mfa/setup/confirm', [
            'setup_token' => $accept->json('data.setup_token'),
            'code' => $invalid,
        ])->assertUnprocessable();
    }

    $this->postJson('/api/admin/v1/auth/mfa/setup/confirm', [
        'setup_token' => $accept->json('data.setup_token'),
        'code' => $current,
    ])->assertStatus(423)->assertJsonPath('code', 'ADMIN_MFA_SETUP_LOCKED');
});

test('password login returns only an MFA challenge and MFA verification issues the admin session', function (): void {
    $admin = createAdminFoundationUser();

    $login = $this->postJson('/api/admin/v1/auth/login', [
        'email' => $admin->email,
        'password' => 'OrbitAdmin!1234',
    ])->assertOk()
        ->assertJsonPath('data.mfa_required', true)
        ->assertJsonMissingPath('data.access_token');

    $code = app(AdminTotpService::class)->currentCode((string) $admin->totp_secret);
    $verify = $this->postJson('/api/admin/v1/auth/mfa/verify', [
        'challenge_token' => $login->json('data.challenge_token'),
        'code' => $code,
    ])->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer');

    expect(AdminSession::query()->where('admin_user_id', $admin->id)->count())->toBe(1);

    $this->withHeader('Authorization', 'Bearer '.$verify->json('data.access_token'))
        ->getJson('/api/admin/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonMissing(['password'])
        ->assertJsonMissing(['totp_secret']);
});

test('invalid MFA attempts persist and lock the challenge after five failures', function (): void {
    $admin = createAdminFoundationUser();
    $login = $this->postJson('/api/admin/v1/auth/login', ['email' => $admin->email, 'password' => 'OrbitAdmin!1234'])->assertOk();
    $challenge = (string) $login->json('data.challenge_token');

    $current = app(AdminTotpService::class)->currentCode((string) $admin->totp_secret);
    $invalid = $current === '000000' ? '000001' : '000000';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/admin/v1/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => $invalid])->assertUnauthorized();
    }

    $this->postJson('/api/admin/v1/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => $invalid])
        ->assertStatus(423)
        ->assertJsonPath('code', 'ADMIN_MFA_CHALLENGE_LOCKED');

    expect(AdminMfaChallenge::query()->sole()->attempts)->toBe(5);
});

test('failed passwords lock the administrator account', function (): void {
    $admin = createAdminFoundationUser();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/admin/v1/auth/login', ['email' => $admin->email, 'password' => 'wrong-password'])->assertUnauthorized();
    }

    expect($admin->fresh()->locked_until)->not->toBeNull();

    $this->postJson('/api/admin/v1/auth/login', ['email' => $admin->email, 'password' => 'OrbitAdmin!1234'])
        ->assertStatus(423)
        ->assertJsonPath('code', 'ADMIN_ACCOUNT_LOCKED');
});

test('recovery codes can satisfy MFA only once', function (): void {
    $admin = createAdminFoundationUser();
    $recovery = app(AdminRecoveryCodeService::class)->regenerate($admin)[0];

    $firstLogin = $this->postJson('/api/admin/v1/auth/login', ['email' => $admin->email, 'password' => 'OrbitAdmin!1234'])->assertOk();
    $this->postJson('/api/admin/v1/auth/mfa/verify', [
        'challenge_token' => $firstLogin->json('data.challenge_token'),
        'code' => $recovery,
    ])->assertOk()->assertJsonPath('data.recovery_code_used', true);

    $secondLogin = $this->postJson('/api/admin/v1/auth/login', ['email' => $admin->email, 'password' => 'OrbitAdmin!1234'])->assertOk();
    $this->postJson('/api/admin/v1/auth/mfa/verify', [
        'challenge_token' => $secondLogin->json('data.challenge_token'),
        'code' => $recovery,
    ])->assertUnauthorized()->assertJsonPath('code', 'ADMIN_MFA_INVALID');
});

test('idle administrator sessions expire and revoke the mapped Sanctum token', function (): void {
    $admin = createAdminFoundationUser();
    $headers = adminFoundationHeaders($admin);
    $session = AdminSession::query()->sole();
    $tokenId = $session->access_token_id;
    $session->forceFill(['idle_expires_at' => now()->subSecond()])->save();

    $this->withHeaders($headers)->getJson('/api/admin/v1/auth/me')
        ->assertUnauthorized()->assertJsonPath('code', 'ADMIN_SESSION_EXPIRED');

    expect($session->fresh()->revoked_at)->not->toBeNull()
        ->and(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse();
});

test('disabled and temporary-expired administrators cannot use existing admin tokens', function (): void {
    $disabled = createAdminFoundationUser();
    $disabledHeaders = adminFoundationHeaders($disabled);
    $disabled->forceFill(['status' => AdminStatus::Disabled, 'disabled_at' => now()])->save();
    $this->withHeaders($disabledHeaders)->getJson('/api/admin/v1/auth/me')->assertForbidden();

    $expired = createAdminFoundationUser(attributes: ['access_expires_at' => now()->subMinute()]);
    $expiredHeaders = adminFoundationHeaders($expired);
    $this->withHeaders($expiredHeaders)->getJson('/api/admin/v1/auth/me')->assertForbidden();
});

test('reactivating expired temporary access requires an explicit new access policy', function (): void {
    $actor = createAdminFoundationUser();
    $target = createAdminFoundationUser('read-only', [
        'status' => AdminStatus::Disabled,
        'disabled_at' => now(),
        'access_expires_at' => now()->subDay(),
    ]);
    $headers = adminFoundationHeaders($actor);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/admins/'.$target->id.'/status', [
        'status' => 'active',
        'reason' => 'Attempt without an explicit access policy',
    ])->assertUnprocessable()->assertJsonPath('code', 'ADMIN_ACCESS_EXPIRY_REQUIRED');

    $this->withHeaders($headers)->patchJson('/api/admin/v1/admins/'.$target->id.'/status', [
        'status' => 'active',
        'access_expires_at' => null,
        'reason' => 'Convert reviewed administrator to permanent access',
    ])->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.access_expires_at', null);
});

test('read only administrators cannot perform admin management actions', function (): void {
    Mail::fake();
    $admin = createAdminFoundationUser('read-only');
    $headers = adminFoundationHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/admins/invitations', [
        'email' => 'blocked@example.com',
        'role_slugs' => ['read-only'],
        'reason' => 'Should not work',
    ])->assertForbidden()->assertJsonPath('code', 'ADMIN_FORBIDDEN');

    Mail::assertNothingSent();
});

test('high risk admin actions require recent reauthentication', function (): void {
    Mail::fake();
    $admin = createAdminFoundationUser();
    $headers = adminFoundationHeaders($admin, false);

    $this->withHeaders($headers)->postJson('/api/admin/v1/admins/invitations', [
        'email' => 'reauth-required@example.com',
        'role_slugs' => ['read-only'],
        'reason' => 'Testing reauthentication',
    ])->assertStatus(428)->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');
});

test('reauthentication refreshes the high risk action window', function (): void {
    Mail::fake();
    $admin = createAdminFoundationUser();
    $headers = adminFoundationHeaders($admin, false);
    $code = app(AdminTotpService::class)->currentCode((string) $admin->totp_secret);

    $this->withHeaders($headers)->postJson('/api/admin/v1/auth/reauthenticate', [
        'password' => 'OrbitAdmin!1234',
        'code' => $code,
    ])->assertOk()->assertJsonPath('data.reauthenticated', true);

    $this->withHeaders($headers)->postJson('/api/admin/v1/admins/invitations', [
        'email' => 'invited@example.com',
        'role_slugs' => ['read-only'],
        'reason' => 'Operations onboarding',
    ])->assertCreated()->assertJsonMissingPath('data.invitation_token');

    Mail::assertSent(AdminInvitationMail::class);
});

test('custom roles use granular permissions and every role remains gated by admin access', function (): void {
    $admin = createAdminFoundationUser();
    $headers = adminFoundationHeaders($admin);

    $created = $this->withHeaders($headers)->postJson('/api/admin/v1/roles', [
        'name' => 'Audit Reviewer',
        'slug' => 'audit-reviewer',
        'permission_slugs' => ['audit.view'],
        'reason' => 'Create a least privilege audit role',
    ])->assertCreated();

    expect($created->json('data.permissions'))->toContain('admin.access')
        ->and(AdminAuditLog::query()->where('action', 'admin.role.created')->exists())->toBeTrue();
});

test('administrators cannot modify permissions on a role assigned to themselves', function (): void {
    $admin = createAdminFoundationUser();
    $headers = adminFoundationHeaders($admin);
    $role = $admin->roles()->firstOrFail();

    $this->withHeaders($headers)->putJson('/api/admin/v1/roles/'.$role->id.'/permissions', [
        'permission_slugs' => ['admin.access', 'roles.manage'],
        'reason' => 'Attempt self role mutation',
    ])->assertStatus(409)->assertJsonPath('code', 'ADMIN_SELF_ROLE_PERMISSION_CHANGE_FORBIDDEN');
});

test('admin deactivation revokes target sessions and records before and after audit state', function (): void {
    $actor = createAdminFoundationUser();
    $target = createAdminFoundationUser('read-only');
    $actorHeaders = adminFoundationHeaders($actor);
    adminFoundationHeaders($target);

    $this->withHeaders($actorHeaders)->patchJson('/api/admin/v1/admins/'.$target->id.'/status', [
        'status' => 'disabled',
        'reason' => 'Security investigation',
    ])->assertOk()
        ->assertJsonPath('data.status', 'disabled')
        ->assertJsonPath('data.sessions_revoked', 1);

    $audit = AdminAuditLog::query()->where('action', 'admin.account.disabled')->sole();
    expect($audit->reason)->toBe('Security investigation')
        ->and($audit->before_state['status'])->toBe('active')
        ->and($audit->after_state['status'])->toBe('disabled');
});

test('authorized security administrators can list and force revoke another admin session', function (): void {
    $security = createAdminFoundationUser('security-administrator');
    $target = createAdminFoundationUser('read-only');
    $securityHeaders = adminFoundationHeaders($security);
    adminFoundationHeaders($target);
    $targetSession = AdminSession::query()->where('admin_user_id', $target->id)->sole();

    $this->withHeaders($securityHeaders)->getJson('/api/admin/v1/admins/'.$target->id.'/sessions')
        ->assertOk()->assertJsonCount(1, 'data.items');

    $this->withHeaders($securityHeaders)->deleteJson('/api/admin/v1/admins/'.$target->id.'/sessions/'.$targetSession->id, [
        'reason' => 'Suspicious session',
    ])->assertOk()->assertJsonPath('data.revoked', true);

    expect($targetSession->fresh()->revoked_at)->not->toBeNull();
});

test('authorized security administrators can force logout all sessions for another administrator', function (): void {
    $security = createAdminFoundationUser('security-administrator');
    $target = createAdminFoundationUser('read-only');
    $securityHeaders = adminFoundationHeaders($security);
    adminFoundationHeaders($target);
    adminFoundationHeaders($target);

    $this->withHeaders($securityHeaders)->postJson('/api/admin/v1/admins/'.$target->id.'/sessions/revoke-all', [
        'reason' => 'Account takeover response',
    ])->assertOk()->assertJsonPath('data.sessions_revoked', 2);

    expect(AdminSession::query()->where('admin_user_id', $target->id)->whereNull('revoked_at')->count())->toBe(0)
        ->and(AdminAuditLog::query()->where('action', 'admin.sessions.force_revoked_all')->exists())->toBeTrue();
});

test('request ids are returned and consequential mutations are auditable', function (): void {
    $admin = createAdminFoundationUser();
    $headers = adminFoundationHeaders($admin) + ['X-Request-Id' => 'orbit-admin-request-1234'];

    $this->withHeaders($headers)->postJson('/api/admin/v1/auth/recovery-codes/regenerate')
        ->assertOk()->assertHeader('X-Request-Id', 'orbit-admin-request-1234')
        ->assertJsonPath('request_id', 'orbit-admin-request-1234');

    expect(AdminAuditLog::query()->where('request_id', 'orbit-admin-request-1234')->exists())->toBeTrue();
});

test('admin audit logger strips secret shaped fields and audit records are immutable', function (): void {
    $admin = createAdminFoundationUser();
    $logger = app(AdminAuditLogger::class);
    $log = $logger->write('admin.test', $admin, metadata: [
        'password' => 'secret',
        'token' => 'secret-token',
        'safe' => 'visible',
        'nested' => ['plaintext' => 'private', 'ok' => true],
    ]);

    expect($log->metadata)->toBe(['safe' => 'visible', 'nested' => ['ok' => true]]);
    expect(fn () => $log->forceFill(['result' => 'tampered'])->save())->toThrow(LogicException::class);
});

test('audit and login history endpoints enforce their own permissions', function (): void {
    $readOnly = createAdminFoundationUser('read-only');
    $security = createAdminFoundationUser('security-administrator');
    $readHeaders = adminFoundationHeaders($readOnly);
    $securityHeaders = adminFoundationHeaders($security);

    $this->withHeaders($readHeaders)->getJson('/api/admin/v1/audit')->assertOk();
    $this->withHeaders($readHeaders)->getJson('/api/admin/v1/security/login-events')->assertForbidden();
    $this->withHeaders($securityHeaders)->getJson('/api/admin/v1/security/login-events')->assertOk();
});

test('default RBAC creates all scoped administrator role types without granting sensitive reveal by default', function (): void {
    expect(AdminRole::query()->count())->toBe(14)
        ->and(AdminPermission::query()->where('slug', 'sensitive_fields.reveal')->where('is_sensitive', true)->exists())->toBeTrue()
        ->and(AdminRole::query()->whereHas('permissions', fn ($query) => $query->where('slug', 'sensitive_fields.reveal'))->exists())->toBeFalse();
});
