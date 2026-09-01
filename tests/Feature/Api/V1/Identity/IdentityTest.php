<?php

declare(strict_types=1);

use App\Models\AccountDeletionRequest;
use App\Models\DataExportRequest;
use App\Models\IdentityDeviceTrust;
use App\Models\IdentityRefreshToken;
use App\Models\IdentitySession;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Modules\Identity\Actions\FinalizeAccountDeletionAction;
use App\Modules\Identity\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function identityAuth(User $user, string $name = 'identity-test'): array
{
    $token = $user->createToken($name)->plainTextToken;

    // Laravel's RequestGuard caches the authenticated user for the lifetime of
    // the test application. Each feature-test HTTP call represents a separate
    // real request, so clear cached guards before switching bearer tokens.
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$token];
}

function identityBearer(string $token): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$token];
}

function identityDevice(User $user, string $suffix = 'a', string $platform = 'ios'): string
{
    $columns = array_flip(Schema::getColumnListing('devices'));
    $id = (string) Str::uuid7();

    $row = [
        'id' => $id,
        'user_id' => $user->getKey(),
        'client_device_id' => 'identity-client-'.$suffix.'-'.$id,
        'platform' => $platform,
        'device_name' => 'Identity Device '.strtoupper($suffix),
        'push_token' => 'push-'.$suffix.'-'.$id,
        'public_identity_key' => 'identity-public-'.$suffix.'-'.$id,
        'public_key' => 'identity-public-'.$suffix.'-'.$id,
        'last_seen_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('devices')->insert(array_intersect_key($row, $columns));

    return $id;
}

test('identity APIs require authentication', function (): void {
    $this->getJson('/api/v1/identity/sessions')->assertUnauthorized();
    $this->getJson('/api/v1/identity/audit-logs')->assertUnauthorized();
    $this->getJson('/api/v1/identity/privacy')->assertUnauthorized();
    $this->getJson('/api/v1/me/devices')->assertUnauthorized();
});

test('it issues a trusted first device session with short access and rotating refresh credentials', function (): void {
    $user = User::factory()->create();
    $deviceId = identityDevice($user);

    $response = $this->withHeaders(identityAuth($user))
        ->postJson('/api/v1/identity/sessions', ['device_id' => $deviceId])
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.session_id', fn ($value): bool => is_string($value) && $value !== '');

    expect((string) $response->json('data.access_token'))->not->toBe('')
        ->and((string) $response->json('data.refresh_token'))->not->toBe('')
        ->and(IdentitySession::query()->count())->toBe(1)
        ->and(IdentityRefreshToken::query()->count())->toBe(1)
        ->and(IdentityDeviceTrust::query()->where('device_id', $deviceId)->value('status'))->toBe('trusted');

    $session = IdentitySession::query()->sole();
    expect($session->access_expires_at?->between(now()->addMinutes(14), now()->addMinutes(16)))->toBeTrue()
        ->and($session->refresh_expires_at?->between(now()->addDays(59), now()->addDays(61)))->toBeTrue();
});

test('a second device requires approval from an existing trusted device', function (): void {
    $user = User::factory()->create();
    $first = identityDevice($user, 'one');
    $second = identityDevice($user, 'two', 'android');
    $headers = identityAuth($user);

    $this->withHeaders($headers)->postJson('/api/v1/identity/sessions', ['device_id' => $first])->assertCreated();
    $this->withHeaders($headers)->postJson('/api/v1/identity/sessions', ['device_id' => $second])->assertStatus(409);

    expect(IdentityDeviceTrust::query()->where('device_id', $second)->value('status'))->toBe('pending');

    $this->withHeaders($headers)
        ->postJson('/api/v1/identity/devices/'.$second.'/approve', ['approver_device_id' => $first])
        ->assertOk()
        ->assertJsonPath('data.status', 'trusted');

    $this->withHeaders($headers)->postJson('/api/v1/identity/sessions', ['device_id' => $second])->assertCreated();
});

test('an untrusted device cannot approve another device', function (): void {
    $user = User::factory()->create();
    $first = identityDevice($user, 'one');
    $second = identityDevice($user, 'two');
    $third = identityDevice($user, 'three');
    $headers = identityAuth($user);

    $this->withHeaders($headers)->postJson('/api/v1/identity/sessions', ['device_id' => $first])->assertCreated();
    $this->withHeaders($headers)->postJson('/api/v1/identity/sessions', ['device_id' => $second])->assertStatus(409);
    $this->withHeaders($headers)->postJson('/api/v1/identity/sessions', ['device_id' => $third])->assertStatus(409);

    $this->withHeaders($headers)
        ->postJson('/api/v1/identity/devices/'.$third.'/approve', ['approver_device_id' => $second])
        ->assertUnprocessable();
});

test('refresh rotates both credentials and invalidates the previous access token', function (): void {
    $user = User::factory()->create();
    $deviceId = identityDevice($user);
    $issue = $this->withHeaders(identityAuth($user))
        ->postJson('/api/v1/identity/sessions', ['device_id' => $deviceId])
        ->assertCreated();

    $sessionId = (string) $issue->json('data.session_id');
    $firstRefresh = (string) $issue->json('data.refresh_token');
    $firstAccessTokenId = IdentitySession::query()->findOrFail($sessionId)->access_token_id;

    $refresh = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $firstRefresh,
        'device_id' => $deviceId,
    ])->assertOk();

    expect((string) $refresh->json('data.refresh_token'))->not->toBe($firstRefresh)
        ->and(IdentityRefreshToken::query()->where('status', 'rotated')->count())->toBe(1)
        ->and(IdentityRefreshToken::query()->where('status', 'active')->count())->toBe(1)
        ->and(DB::table('personal_access_tokens')->where('id', $firstAccessTokenId)->exists())->toBeFalse();
});

test('refresh token replay revokes the whole device session family', function (): void {
    $user = User::factory()->create();
    $deviceId = identityDevice($user);
    $issue = $this->withHeaders(identityAuth($user))
        ->postJson('/api/v1/identity/sessions', ['device_id' => $deviceId])
        ->assertCreated();

    $oldRefresh = (string) $issue->json('data.refresh_token');
    $sessionId = (string) $issue->json('data.session_id');

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $oldRefresh,
        'device_id' => $deviceId,
    ])->assertOk();

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $oldRefresh,
        'device_id' => $deviceId,
    ])->assertUnauthorized();

    expect(IdentitySession::query()->findOrFail($sessionId)->status)->toBe('revoked')
        ->and(IdentityRefreshToken::query()->where('family_id', IdentitySession::query()->findOrFail($sessionId)->refresh_family_id)->where('status', 'active')->count())->toBe(0)
        ->and(SecurityAuditLog::query()->where('action', 'identity.session.security_revocation')->exists())->toBeTrue();
});

test('session listing is private to the authenticated user', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();
    $device = identityDevice($one);

    $this->withHeaders(identityAuth($one))->postJson('/api/v1/identity/sessions', ['device_id' => $device])->assertCreated();

    $this->withHeaders(identityAuth($two))
        ->getJson('/api/v1/identity/sessions')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a user can revoke an owned session but cannot revoke another users session', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();
    $device = identityDevice($one);

    $issue = $this->withHeaders(identityAuth($one))
        ->postJson('/api/v1/identity/sessions', ['device_id' => $device])
        ->assertCreated();

    $sessionId = (string) $issue->json('data.session_id');

    $this->withHeaders(identityAuth($two))->deleteJson('/api/v1/identity/sessions/'.$sessionId)->assertNotFound();
    $this->withHeaders(identityAuth($one))->deleteJson('/api/v1/identity/sessions/'.$sessionId)->assertOk();

    expect(IdentitySession::query()->findOrFail($sessionId)->status)->toBe('revoked');
});

test('identity logout revokes the mapped secure session', function (): void {
    $user = User::factory()->create();
    $deviceId = identityDevice($user);
    $issue = $this->withHeaders(identityAuth($user))
        ->postJson('/api/v1/identity/sessions', ['device_id' => $deviceId])
        ->assertCreated();

    $sessionId = (string) $issue->json('data.session_id');
    $access = (string) $issue->json('data.access_token');

    $this->withHeaders(identityBearer($access))
        ->postJson('/api/v1/identity/logout')
        ->assertOk()
        ->assertJsonPath('data.signed_out', true);

    expect(IdentitySession::query()->findOrFail($sessionId)->status)->toBe('revoked');
});

test('device listing and rename expose only owned safe device metadata', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $deviceId = identityDevice($user, 'mine');
    identityDevice($other, 'other');
    $headers = identityAuth($user);

    $this->withHeaders($headers)
        ->getJson('/api/v1/me/devices')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $deviceId);

    $this->withHeaders($headers)
        ->putJson('/api/v1/me/devices/'.$deviceId.'/name', ['device_name' => 'My Phone'])
        ->assertOk()
        ->assertJsonPath('data.device_name', 'My Phone');

    expect(DB::table('devices')->where('id', $deviceId)->value('device_name'))->toBe('My Phone');
});

test('audit log API is private and strips secret-shaped metadata', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();

    app(AuditLogger::class)->write('identity.test', (int) $one->id, metadata: [
        'token' => 'secret',
        'safe' => 'visible',
        'nested' => ['plaintext' => 'private', 'ok' => true],
    ]);
    app(AuditLogger::class)->write('identity.other', (int) $two->id);

    $this->withHeaders(identityAuth($one))
        ->getJson('/api/v1/identity/audit-logs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.metadata.safe', 'visible')
        ->assertJsonMissing(['secret'])
        ->assertJsonMissing(['private']);
});

test('data export is idempotent while active and contains only server-visible safe account data', function (): void {
    $user = User::factory()->create();
    identityDevice($user);
    $headers = identityAuth($user);

    $first = $this->withHeaders($headers)->postJson('/api/v1/identity/data-exports')->assertCreated();
    $second = $this->withHeaders($headers)->postJson('/api/v1/identity/data-exports')->assertCreated();

    expect($first->json('data.id'))->toBe($second->json('data.id'))
        ->and(DataExportRequest::query()->count())->toBe(1);

    $this->withHeaders($headers)
        ->getJson('/api/v1/identity/data-exports/'.$first->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.payload.profile.id', $user->id)
        ->assertJsonMissing(['password'])
        ->assertJsonMissing(['ciphertext']);
});

test('a data export cannot be read by another user', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();

    $export = $this->withHeaders(identityAuth($one))
        ->postJson('/api/v1/identity/data-exports')
        ->assertCreated()
        ->json('data.id');

    $this->withHeaders(identityAuth($two))
        ->getJson('/api/v1/identity/data-exports/'.$export)
        ->assertNotFound();
});

test('account deletion has a thirty day reversible grace period and is idempotent', function (): void {
    $user = User::factory()->create();
    $headers = identityAuth($user);

    $first = $this->withHeaders($headers)
        ->postJson('/api/v1/identity/account-deletion', ['reason' => 'privacy'])
        ->assertStatus(202);

    $second = $this->withHeaders($headers)
        ->postJson('/api/v1/identity/account-deletion', ['reason' => 'duplicate'])
        ->assertStatus(202);

    expect($first->json('data.id'))->toBe($second->json('data.id'))
        ->and(AccountDeletionRequest::query()->count())->toBe(1)
        ->and(now()->diffInDays(AccountDeletionRequest::query()->sole()->scheduled_for, false))->toBeGreaterThanOrEqual(29);
});

test('account deletion can be cancelled during the grace period', function (): void {
    $user = User::factory()->create();
    $headers = identityAuth($user);

    $this->withHeaders($headers)->postJson('/api/v1/identity/account-deletion')->assertStatus(202);
    $this->withHeaders($headers)
        ->deleteJson('/api/v1/identity/account-deletion')
        ->assertOk()
        ->assertJsonPath('data.cancelled', true)
        ->assertJsonPath('data.status', 'cancelled');

    expect(DB::table('users')->where('id', $user->id)->value('account_deletion_scheduled_for'))->toBeNull();
});

test('deletion finalization blocks a Circle owner until ownership is transferred', function (): void {
    $user = User::factory()->create();
    $headers = identityAuth($user);
    $this->withHeaders($headers)->postJson('/api/v1/circles', ['name' => 'Owned Circle'])->assertCreated();

    $deletion = AccountDeletionRequest::query()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'requested_at' => now()->subDays(31),
        'scheduled_for' => now()->subDay(),
    ]);

    $result = app(FinalizeAccountDeletionAction::class)->handle($deletion);

    expect($result)->toBe('blocked_owner')
        ->and($deletion->fresh()->status)->toBe('blocked')
        ->and($deletion->fresh()->blocking_reason)->not->toBeNull();
});

test('deletion finalization pseudonymizes a non-owner and revokes sessions and push delivery', function (): void {
    $user = User::factory()->create();
    $deviceId = identityDevice($user);
    $issue = $this->withHeaders(identityAuth($user))
        ->postJson('/api/v1/identity/sessions', ['device_id' => $deviceId])
        ->assertCreated();

    $sessionId = (string) $issue->json('data.session_id');
    $accessTokenId = IdentitySession::query()->findOrFail($sessionId)->access_token_id;

    $deletion = AccountDeletionRequest::query()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'requested_at' => now()->subDays(31),
        'scheduled_for' => now()->subDay(),
    ]);

    expect(app(FinalizeAccountDeletionAction::class)->handle($deletion))->toBe('completed')
        ->and(IdentitySession::query()->findOrFail($sessionId)->status)->toBe('revoked')
        ->and(DB::table('personal_access_tokens')->where('id', $accessTokenId)->exists())->toBeFalse()
        ->and(DB::table('devices')->where('id', $deviceId)->value('push_token'))->toBeNull()
        ->and(DB::table('users')->where('id', $user->id)->value('account_deleted_at'))->not->toBeNull()
        ->and((string) DB::table('users')->where('id', $user->id)->value('email'))->toContain('@orbit.invalid');
});

test('privacy summary shows only the authenticated users visibility and lifecycle state', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    $circleResponse = $this->withHeaders(identityAuth($owner))
        ->postJson('/api/v1/circles', ['name' => 'Private Circle'])
        ->assertCreated();

    $circleId = data_get($circleResponse->json(), 'data.id')
        ?? data_get($circleResponse->json(), 'data.circle.id')
        ?? data_get($circleResponse->json(), 'id');

    $this->withHeaders(identityAuth($owner))
        ->getJson('/api/v1/identity/privacy')
        ->assertOk()
        ->assertJsonPath('data.circles.0.circle_id', (string) $circleId);

    $this->withHeaders(identityAuth($outsider))
        ->getJson('/api/v1/identity/privacy')
        ->assertOk()
        ->assertJsonCount(0, 'data.circles');
});
