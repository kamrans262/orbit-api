<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Device;
use App\Models\Message;
use App\Models\MessageEnvelope;
use App\Models\MessagingPreference;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Messaging\Enums\MessageType;
use App\Modules\Realtime\Broadcasts\MessageDeliveredBroadcast;
use App\Modules\Realtime\Broadcasts\MessageEnvelopeAvailableBroadcast;
use App\Modules\Realtime\Broadcasts\MessageReadBroadcast;
use App\Modules\Realtime\Broadcasts\TypingIndicatorBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createMessagingCircle(User $owner, User $member, LocationMode $memberMode = LocationMode::Hidden): array
{
    $circle = Circle::query()->create([
        'created_by' => $owner->id,
        'name' => 'Encrypted Chat',
        'type' => 'standard',
    ]);

    $ownerMembership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $owner->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Hidden,
        'can_message' => true,
        'joined_at' => now(),
    ]);

    $memberMembership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => $memberMode,
        'can_message' => true,
        'joined_at' => now()->addSecond(),
    ]);

    return [$circle, $ownerMembership, $memberMembership];
}

function createMessagingDevice(User $user, string $suffix, ?string $key = null): Device
{
    return Device::query()->create([
        'user_id' => $user->id,
        'client_device_id' => 'messaging-'.$suffix,
        'platform' => 'android',
        'name' => 'Test Device',
        'public_identity_key' => $key ?? 'public-key-'.$suffix,
        'last_seen_at' => now(),
    ]);
}

function encryptedMessagePayload(Device $sender, array $recipientDevices, ?string $messageId = null): array
{
    return [
        'message_id' => $messageId ?? (string) Str::uuid(),
        'sender_device_id' => $sender->id,
        'type' => 'text',
        'client_sent_at' => now()->toIso8601String(),
        'envelopes' => collect($recipientDevices)->map(fn (Device $device): array => [
            'envelope_id' => (string) Str::uuid(),
            'recipient_device_id' => $device->id,
            'ciphertext' => 'ciphertext-for-'.$device->id,
            'encrypted_preview' => 'encrypted-preview',
        ])->values()->all(),
    ];
}

function createPendingMessagingEnvelope(User $sender, User $recipient): array
{
    [$circle] = createMessagingCircle($sender, $recipient);
    $senderDevice = createMessagingDevice($sender, 'sender-'.Str::random(5));
    $recipientDevice = createMessagingDevice($recipient, 'recipient-'.Str::random(5));

    $message = Message::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'sender_user_id' => $sender->id,
        'sender_device_id' => $senderDevice->id,
        'type' => MessageType::Text,
        'expires_at' => now()->addDays(30),
    ]);

    $envelope = MessageEnvelope::query()->create([
        'envelope_id' => (string) Str::uuid(),
        'message_id' => $message->id,
        'recipient_user_id' => $recipient->id,
        'recipient_device_id' => $recipientDevice->id,
        'ciphertext' => 'opaque-ciphertext',
        'encrypted_preview' => 'opaque-preview',
        'expires_at' => now()->addDays(30),
    ]);

    return [$circle, $senderDevice, $recipientDevice, $message, $envelope];
}

it('requires authentication for Circle message device keys', function (): void {
    $this->getJson('/api/v1/circles/'.Str::uuid().'/message-devices')->assertUnauthorized();
});

it('returns only active encryption-ready devices to Circle members', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createMessagingCircle($owner, $member);

    $ready = createMessagingDevice($member, 'ready');
    createMessagingDevice($member, 'no-key', null)->forceFill(['public_identity_key' => null])->save();
    createMessagingDevice($member, 'revoked')->forceFill(['revoked_at' => now()])->save();

    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/circles/'.$circle->id.'/message-devices')
        ->assertOk()
        ->assertJsonFragment(['device_id' => $ready->id])
        ->assertJsonMissing(['client_device_id' => 'messaging-no-key'])
        ->assertJsonMissing(['client_device_id' => 'messaging-revoked']);
});

it('accepts only ciphertext envelopes and broadcasts each recipient device envelope', function (): void {
    Event::fake([MessageEnvelopeAvailableBroadcast::class]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createMessagingCircle($owner, $member);
    $senderDevice = createMessagingDevice($owner, 'sender');
    $recipientDevice = createMessagingDevice($member, 'recipient');
    Sanctum::actingAs($owner);

    $payload = encryptedMessagePayload($senderDevice, [$recipientDevice]);

    $this->postJson('/api/v1/circles/'.$circle->id.'/messages', $payload)
        ->assertCreated()
        ->assertJsonPath('data.duplicate', false)
        ->assertJsonPath('data.message.id', $payload['message_id'])
        ->assertJsonMissingPath('data.message.plaintext');

    $this->assertDatabaseHas('message_envelopes', [
        'message_id' => $payload['message_id'],
        'recipient_device_id' => $recipientDevice->id,
        'ciphertext' => 'ciphertext-for-'.$recipientDevice->id,
    ]);

    Event::assertDispatched(MessageEnvelopeAvailableBroadcast::class, fn ($event): bool => $event->envelope->recipient_device_id === $recipientDevice->id
        && $event->broadcastOn()->name === 'private-devices.'.$recipientDevice->id
        && $event->broadcastAs() === 'message.received'
    );
});

it('rejects stale recipient device sets so clients re-encrypt for the current Circle devices', function (): void {
    Event::fake([MessageEnvelopeAvailableBroadcast::class]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createMessagingCircle($owner, $member);
    $senderDevice = createMessagingDevice($owner, 'sender-stale');
    $recipientOne = createMessagingDevice($member, 'recipient-one');
    createMessagingDevice($member, 'recipient-two');
    Sanctum::actingAs($owner);

    $payload = encryptedMessagePayload($senderDevice, [$recipientOne]);

    $this->postJson('/api/v1/circles/'.$circle->id.'/messages', $payload)
        ->assertStatus(409)
        ->assertJsonPath('code', 'MESSAGING_RECIPIENT_DEVICES_CHANGED');
});

it('is idempotent when the client retries the same message ID', function (): void {
    Event::fake([MessageEnvelopeAvailableBroadcast::class]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createMessagingCircle($owner, $member);
    $senderDevice = createMessagingDevice($owner, 'sender-idempotent');
    $recipient = createMessagingDevice($member, 'recipient-idempotent');
    Sanctum::actingAs($owner);

    $payload = encryptedMessagePayload($senderDevice, [$recipient]);

    $this->postJson('/api/v1/circles/'.$circle->id.'/messages', $payload)->assertCreated();
    $this->postJson('/api/v1/circles/'.$circle->id.'/messages', $payload)
        ->assertOk()
        ->assertJsonPath('data.duplicate', true);

    expect(Message::query()->whereKey($payload['message_id'])->count())->toBe(1);
    expect(MessageEnvelope::query()->where('message_id', $payload['message_id'])->count())->toBe(1);
});

it('syncs only pending encrypted envelopes for the authenticated users device', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    [, , $recipientDevice, , $envelope] = createPendingMessagingEnvelope($sender, $recipient);
    Sanctum::actingAs($recipient);

    $this->getJson('/api/v1/messages/sync?device_id='.$recipientDevice->id.'&after_id=0&limit=200')
        ->assertOk()
        ->assertJsonCount(1, 'data.envelopes')
        ->assertJsonPath('data.envelopes.0.envelope_id', $envelope->envelope_id)
        ->assertJsonPath('data.envelopes.0.ciphertext', 'opaque-ciphertext');
});

it('does not allow another user to sync a device they do not own', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $other = User::factory()->create();
    [, , $recipientDevice] = createPendingMessagingEnvelope($sender, $recipient);
    Sanctum::actingAs($other);

    $this->getJson('/api/v1/messages/sync?device_id='.$recipientDevice->id)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'MESSAGING_INVALID_DEVICE');
});

it('acknowledges delivery idempotently, removes ciphertext, and broadcasts metadata to the sender', function (): void {
    Event::fake([MessageDeliveredBroadcast::class]);

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    [, , $recipientDevice, $message, $envelope] = createPendingMessagingEnvelope($sender, $recipient);
    Sanctum::actingAs($recipient);

    $url = '/api/v1/message-envelopes/'.$envelope->envelope_id.'/delivered';

    $this->postJson($url)
        ->assertOk()
        ->assertJsonPath('data.duplicate', false)
        ->assertJsonPath('data.message_id', $message->id);

    $this->assertDatabaseMissing('message_envelopes', ['envelope_id' => $envelope->envelope_id]);
    $this->assertDatabaseHas('message_delivery_receipts', ['envelope_id' => $envelope->envelope_id]);

    $this->postJson($url)
        ->assertOk()
        ->assertJsonPath('data.duplicate', true);

    Event::assertDispatched(MessageDeliveredBroadcast::class, fn ($event): bool => $event->messageId === $message->id
        && $event->recipientDeviceId === $recipientDevice->id
        && $event->broadcastOn()->name === 'private-users.'.$sender->id
    );
});

it('broadcasts read receipts by default and deduplicates them', function (): void {
    Event::fake([MessageReadBroadcast::class]);

    $sender = User::factory()->create();
    $reader = User::factory()->create();
    [$circle, , , $message] = createPendingMessagingEnvelope($sender, $reader);
    Sanctum::actingAs($reader);

    $url = '/api/v1/circles/'.$circle->id.'/messages/'.$message->id.'/read';

    $this->postJson($url)
        ->assertOk()
        ->assertJsonPath('data.broadcasted', true)
        ->assertJsonPath('data.duplicate', false);

    $this->postJson($url)
        ->assertOk()
        ->assertJsonPath('data.broadcasted', false)
        ->assertJsonPath('data.duplicate', true);

    Event::assertDispatchedTimes(MessageReadBroadcast::class, 1);
});

it('respects a users read receipt opt-out', function (): void {
    Event::fake([MessageReadBroadcast::class]);

    $sender = User::factory()->create();
    $reader = User::factory()->create();
    [$circle, , , $message] = createPendingMessagingEnvelope($sender, $reader);
    MessagingPreference::query()->create([
        'user_id' => $reader->id,
        'read_receipts_enabled' => false,
    ]);
    Sanctum::actingAs($reader);

    $this->postJson('/api/v1/circles/'.$circle->id.'/messages/'.$message->id.'/read')
        ->assertOk()
        ->assertJsonPath('data.broadcasted', false);

    Event::assertNotDispatched(MessageReadBroadcast::class);
    $this->assertDatabaseMissing('message_read_receipts', [
        'message_id' => $message->id,
        'user_id' => $reader->id,
    ]);
});

it('broadcasts ephemeral typing indicators and throttles repeated typing starts', function (): void {
    Event::fake([TypingIndicatorBroadcast::class]);
    Cache::flush();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createMessagingCircle($owner, $member);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/circles/'.$circle->id.'/typing', ['is_typing' => true])
        ->assertOk()
        ->assertJsonPath('data.broadcasted', true);

    $this->postJson('/api/v1/circles/'.$circle->id.'/typing', ['is_typing' => true])
        ->assertOk()
        ->assertJsonPath('data.broadcasted', false)
        ->assertJsonPath('data.suppressed_reason', 'throttled');

    Event::assertDispatchedTimes(TypingIndicatorBroadcast::class, 1);
});

it('suppresses typing indicators while the member is in Circle Ghost Mode', function (): void {
    Event::fake([TypingIndicatorBroadcast::class]);
    Cache::flush();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createMessagingCircle($owner, $member, LocationMode::Ghost);
    Sanctum::actingAs($member);

    $this->postJson('/api/v1/circles/'.$circle->id.'/typing', ['is_typing' => true])
        ->assertOk()
        ->assertJsonPath('data.broadcasted', false)
        ->assertJsonPath('data.suppressed_reason', 'ghost_mode');

    Event::assertNotDispatched(TypingIndicatorBroadcast::class);
});

it('purges expired server message metadata and pending ciphertext envelopes', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    [, , , $message] = createPendingMessagingEnvelope($sender, $recipient);
    $message->forceFill(['expires_at' => now()->subSecond()])->save();

    $this->artisan('orbit:messages:purge-expired')->assertSuccessful();

    $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    $this->assertDatabaseMissing('message_envelopes', ['message_id' => $message->id]);
});
