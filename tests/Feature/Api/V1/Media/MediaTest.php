<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Device;
use App\Models\MediaAsset;
use App\Models\MediaKeyEnvelope;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createMediaContext(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $circle = Circle::query()->create([
        'created_by' => $owner->id,
        'name' => 'Encrypted Media Circle',
        'type' => 'standard',
    ]);

    CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $owner->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Hidden,
        'can_message' => true,
        'joined_at' => now(),
    ]);

    CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => LocationMode::Hidden,
        'can_message' => true,
        'joined_at' => now(),
    ]);

    $ownerDevice = Device::query()->create([
        'user_id' => $owner->id,
        'client_device_id' => 'media-'.Str::uuid(),
        'platform' => 'android',
        'name' => 'Owner Phone',
        'public_identity_key' => 'owner-public-key',
    ]);

    $memberDevice = Device::query()->create([
        'user_id' => $member->id,
        'client_device_id' => 'media-'.Str::uuid(),
        'platform' => 'android',
        'name' => 'Member Phone',
        'public_identity_key' => 'member-public-key',
    ]);

    return [$owner, $member, $circle, $ownerDevice, $memberDevice];
}

it('requires authentication to create a media upload', function (): void {
    $this->postJson('/api/v1/circles/'.Str::uuid().'/media/uploads', [])
        ->assertUnauthorized();
});

it('creates an encrypted media upload without plaintext metadata', function (): void {
    [$owner, , $circle, $ownerDevice] = createMediaContext();
    Sanctum::actingAs($owner);

    $ciphertext = random_bytes(2000);

    $this->postJson('/api/v1/circles/'.$circle->id.'/media/uploads', [
        'asset_id' => (string) Str::uuid(),
        'uploader_device_id' => $ownerDevice->id,
        'kind' => 'image',
        'content_type_hint' => 'application/octet-stream',
        'size_bytes' => strlen($ciphertext),
        'sha256_ciphertext' => hash('sha256', $ciphertext),
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonMissingPath('data.filename')
        ->assertJsonMissingPath('data.caption');
});

it('rejects an upload from a device not owned by the user', function (): void {
    [$owner, , $circle, , $memberDevice] = createMediaContext();
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/circles/'.$circle->id.'/media/uploads', [
        'asset_id' => (string) Str::uuid(),
        'uploader_device_id' => $memberDevice->id,
        'kind' => 'image',
        'size_bytes' => 100,
        'sha256_ciphertext' => str_repeat('a', 64),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'MEDIA_INVALID_DEVICE');
});

it('completes encrypted media and stores only key envelopes per current device', function (): void {
    Storage::fake('local');
    config()->set('orbit_media.disk', 'local');
    config()->set('orbit_media.chunk_size_bytes', 262144);

    [$owner, , $circle, $ownerDevice, $memberDevice] = createMediaContext();
    Sanctum::actingAs($owner);

    $ciphertext = random_bytes(300000);
    $assetId = (string) Str::uuid();

    $create = $this->postJson('/api/v1/circles/'.$circle->id.'/media/uploads', [
        'asset_id' => $assetId,
        'uploader_device_id' => $ownerDevice->id,
        'kind' => 'image',
        'size_bytes' => strlen($ciphertext),
        'sha256_ciphertext' => hash('sha256', $ciphertext),
    ])->assertCreated();

    $uploadId = $create->json('data.upload_id');

    $chunkSize = (int) $create->json('data.chunk_size_bytes');

    foreach (str_split($ciphertext, $chunkSize) as $index => $chunk) {
        $this->call(
            'PUT',
            '/api/v1/media/uploads/'.$uploadId.'/chunks/'.$index,
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CHUNK_SHA256' => hash('sha256', $chunk),
                'CONTENT_TYPE' => 'application/octet-stream',
            ],
            $chunk,
        )->assertOk();
    }

    $this->postJson('/api/v1/media/uploads/'.$uploadId.'/complete', [
        'key_envelopes' => [
            [
                'recipient_device_id' => $ownerDevice->id,
                'algorithm' => 'x25519-xsalsa20-poly1305',
                'encrypted_key' => 'encrypted-key-owner',
            ],
            [
                'recipient_device_id' => $memberDevice->id,
                'algorithm' => 'x25519-xsalsa20-poly1305',
                'encrypted_key' => 'encrypted-key-member',
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.asset_id', $assetId)
        ->assertJsonPath('data.sha256_ciphertext', hash('sha256', $ciphertext));

    $this->assertDatabaseHas('media_assets', [
        'id' => $assetId,
        'sha256_ciphertext' => hash('sha256', $ciphertext),
        'size_bytes' => strlen($ciphertext),
        'status' => 'ready',
    ]);

    $this->assertDatabaseCount('media_key_envelopes', 2);
    Storage::disk('local')->assertExists('orbit-media/assets/'.$assetId.'/ciphertext.bin');
});

it('rejects completion when the recipient device set is stale', function (): void {
    Storage::fake('local');
    config()->set('orbit_media.disk', 'local');
    config()->set('orbit_media.chunk_size_bytes', 1024);

    [$owner, , $circle, $ownerDevice] = createMediaContext();
    Sanctum::actingAs($owner);

    $ciphertext = random_bytes(500);

    $create = $this->postJson('/api/v1/circles/'.$circle->id.'/media/uploads', [
        'asset_id' => (string) Str::uuid(),
        'uploader_device_id' => $ownerDevice->id,
        'kind' => 'image',
        'size_bytes' => strlen($ciphertext),
        'sha256_ciphertext' => hash('sha256', $ciphertext),
    ])->assertCreated();

    $uploadId = $create->json('data.upload_id');

    $this->call(
        'PUT',
        '/api/v1/media/uploads/'.$uploadId.'/chunks/0',
        [],
        [],
        [],
        ['HTTP_ACCEPT' => 'application/json'],
        $ciphertext,
    )->assertOk();

    $this->postJson('/api/v1/media/uploads/'.$uploadId.'/complete', [
        'key_envelopes' => [[
            'recipient_device_id' => $ownerDevice->id,
            'algorithm' => 'x25519-xsalsa20-poly1305',
            'encrypted_key' => 'owner-only',
        ]],
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'MEDIA_STALE_DEVICE_SET');
});

it('allows only the intended device to obtain its media key envelope', function (): void {
    [$owner, $member, $circle, $ownerDevice, $memberDevice] = createMediaContext();

    $asset = MediaAsset::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'uploader_user_id' => $owner->id,
        'uploader_device_id' => $ownerDevice->id,
        'kind' => 'image',
        'storage_disk' => 'local',
        'storage_path' => 'fake/path',
        'size_bytes' => 10,
        'sha256_ciphertext' => str_repeat('a', 64),
        'status' => 'ready',
    ]);

    MediaKeyEnvelope::query()->create([
        'media_asset_id' => $asset->id,
        'recipient_device_id' => $memberDevice->id,
        'algorithm' => 'test',
        'encrypted_key' => 'member-secret-envelope',
    ]);

    Sanctum::actingAs($member);

    $this->getJson('/api/v1/media/'.$asset->id.'/key-envelope?device_id='.$memberDevice->id)
        ->assertOk()
        ->assertJsonPath('data.encrypted_key', 'member-secret-envelope');

    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/media/'.$asset->id.'/key-envelope?device_id='.$memberDevice->id)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'MEDIA_INVALID_DEVICE');
});

it('allows only the uploader to delete encrypted media', function (): void {
    Storage::fake('local');
    [$owner, $member, $circle, $ownerDevice] = createMediaContext();

    $asset = MediaAsset::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'uploader_user_id' => $owner->id,
        'uploader_device_id' => $ownerDevice->id,
        'kind' => 'image',
        'storage_disk' => 'local',
        'storage_path' => 'orbit-media/assets/example/ciphertext.bin',
        'size_bytes' => 10,
        'sha256_ciphertext' => str_repeat('a', 64),
        'status' => 'ready',
    ]);

    Storage::disk('local')->put($asset->storage_path, 'ciphertext');

    Sanctum::actingAs($member);
    $this->deleteJson('/api/v1/media/'.$asset->id)
        ->assertForbidden();

    Sanctum::actingAs($owner);
    $this->deleteJson('/api/v1/media/'.$asset->id)
        ->assertOk();

    Storage::disk('local')->assertMissing($asset->storage_path);
    $this->assertDatabaseHas('media_assets', [
        'id' => $asset->id,
        'status' => 'deleted',
    ]);
});
