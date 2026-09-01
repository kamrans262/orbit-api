<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication to register a device', function (): void {
    $this->postJson('/api/v1/devices', [
        'client_device_id' => 'device-001',
        'platform' => 'android',
    ])->assertUnauthorized();
});

it('registers a device for the authenticated user', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/devices', [
        'client_device_id' => 'device-001',
        'platform' => 'android',
        'name' => 'Pixel Test Device',
        'app_version' => '1.0.0',
        'os_version' => 'Android 16',
        'public_identity_key' => 'public-key-value',
        'push_token' => 'push-token-value',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.client_device_id', 'device-001')
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.has_public_identity_key', true)
        ->assertJsonPath('data.has_push_token', true);

    $this->assertDatabaseHas('devices', [
        'user_id' => $user->id,
        'client_device_id' => 'device-001',
        'platform' => 'android',
    ]);
});

it('updates the same device instead of creating duplicates', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $payload = [
        'client_device_id' => 'device-001',
        'platform' => 'android',
        'name' => 'First Name',
    ];

    $this->postJson('/api/v1/devices', $payload)->assertOk();

    $payload['name'] = 'Updated Name';
    $this->postJson('/api/v1/devices', $payload)->assertOk();

    expect(Device::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Device::query()->firstOrFail()->name)->toBe('Updated Name');
});

it('lists only devices owned by the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Device::query()->create([
        'user_id' => $user->id,
        'client_device_id' => 'mine',
        'platform' => 'ios',
        'last_seen_at' => now(),
    ]);

    Device::query()->create([
        'user_id' => $otherUser->id,
        'client_device_id' => 'other',
        'platform' => 'android',
        'last_seen_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/devices')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.client_device_id', 'mine');
});

it('revokes an owned device and removes its push token', function (): void {
    $user = User::factory()->create();
    $device = Device::query()->create([
        'user_id' => $user->id,
        'client_device_id' => 'device-001',
        'platform' => 'android',
        'push_token' => 'secret-push-token',
        'last_seen_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/devices/'.$device->id)
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked')
        ->assertJsonPath('data.has_push_token', false);

    $device->refresh();

    expect($device->revoked_at)->not->toBeNull()
        ->and($device->push_token)->toBeNull();
});

it('does not allow a user to revoke another users device', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $device = Device::query()->create([
        'user_id' => $otherUser->id,
        'client_device_id' => 'other-device',
        'platform' => 'android',
    ]);

    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/devices/'.$device->id)
        ->assertNotFound()
        ->assertJsonPath('code', 'DEVICE_NOT_FOUND');

    expect($device->fresh()->revoked_at)->toBeNull();
});
