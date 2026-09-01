<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Ping;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Ping\Enums\PingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createPingCircleFor(User $owner, User $member, bool $canPing = true): array
{
    $circle = Circle::query()->create([
        'created_by' => $owner->id,
        'name' => 'Ping Circle',
        'type' => 'standard',
    ]);

    $ownerMembership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $owner->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    $memberMembership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => LocationMode::Hidden,
        'can_ping' => $canPing,
        'joined_at' => now()->addSecond(),
    ]);

    return [$circle, $ownerMembership, $memberMembership];
}

function createPendingPing(
    Circle $circle,
    CircleMember $sender,
    CircleMember $recipient,
    array $attributes = [],
): Ping {
    return Ping::query()->create(array_merge([
        'circle_id' => $circle->id,
        'sender_membership_id' => $sender->id,
        'recipient_membership_id' => $recipient->id,
        'status' => PingStatus::Pending,
        'expires_at' => now()->addMinutes(2),
    ], $attributes));
}

it('requires authentication to send a Ping', function (): void {
    $this->postJson('/api/v1/pings', [
        'circle_id' => fake()->uuid(),
        'recipient_membership_id' => fake()->uuid(),
    ])->assertUnauthorized();
});

it('sends a Ping to another member of the same Circle', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createPingCircleFor($owner, $member);
    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/v1/pings', [
        'circle_id' => $circle->id,
        'recipient_membership_id' => $memberMembership->id,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.circle.id', $circle->id)
        ->assertJsonPath('data.sender.membership_id', $ownerMembership->id)
        ->assertJsonPath('data.recipient.membership_id', $memberMembership->id)
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('pings', [
        'circle_id' => $circle->id,
        'sender_membership_id' => $ownerMembership->id,
        'recipient_membership_id' => $memberMembership->id,
        'status' => 'pending',
    ]);
});

it('does not allow a user to Ping themselves', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership] = createPingCircleFor($owner, $member);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/pings', [
        'circle_id' => $circle->id,
        'recipient_membership_id' => $ownerMembership->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'PING_SELF_NOT_ALLOWED');
});

it('respects a recipients can_ping privacy setting', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $memberMembership] = createPingCircleFor($owner, $member, false);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/pings', [
        'circle_id' => $circle->id,
        'recipient_membership_id' => $memberMembership->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'PING_DISABLED');
});

it('does not allow Ping targets from a different Circle', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherOwner = User::factory()->create();

    [$circle] = createPingCircleFor($owner, $member);
    [, , $otherMembership] = createPingCircleFor($otherOwner, User::factory()->create());

    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/pings', [
        'circle_id' => $circle->id,
        'recipient_membership_id' => $otherMembership->id,
    ])
        ->assertNotFound()
        ->assertJsonPath('code', 'PING_RECIPIENT_NOT_FOUND');
});

it('prevents accidental rapid duplicate Pings', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $memberMembership] = createPingCircleFor($owner, $member);
    Sanctum::actingAs($owner);

    $payload = [
        'circle_id' => $circle->id,
        'recipient_membership_id' => $memberMembership->id,
    ];

    $this->postJson('/api/v1/pings', $payload)->assertCreated();

    $this->postJson('/api/v1/pings', $payload)
        ->assertStatus(429)
        ->assertJsonPath('code', 'PING_COOLDOWN');
});

it('lists only active received Pings in the inbox', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createPingCircleFor($owner, $member);

    $active = createPendingPing($circle, $ownerMembership, $memberMembership);
    createPendingPing($circle, $memberMembership, $ownerMembership);
    createPendingPing($circle, $ownerMembership, $memberMembership, [
        'expires_at' => now()->subSecond(),
    ]);

    Sanctum::actingAs($member);

    $this->getJson('/api/v1/pings/inbox')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);
});

it('lists Pings sent by the authenticated user', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createPingCircleFor($owner, $member);

    $sent = createPendingPing($circle, $ownerMembership, $memberMembership);
    createPendingPing($circle, $memberMembership, $ownerMembership);

    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/pings/sent')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $sent->id);
});

it('allows the recipient to respond with Hey', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createPingCircleFor($owner, $member);
    $ping = createPendingPing($circle, $ownerMembership, $memberMembership);

    Sanctum::actingAs($member);

    $this->postJson('/api/v1/pings/'.$ping->id.'/respond', [
        'response_type' => 'hey',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'responded')
        ->assertJsonPath('data.response_type', 'hey');

    $this->assertDatabaseHas('pings', [
        'id' => $ping->id,
        'status' => 'responded',
        'response_type' => 'hey',
    ]);
});

it('allows the recipient to respond with Share Location intent', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createPingCircleFor($owner, $member);
    $ping = createPendingPing($circle, $ownerMembership, $memberMembership);

    Sanctum::actingAs($member);

    $this->postJson('/api/v1/pings/'.$ping->id.'/respond', [
        'response_type' => 'share_location',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'responded')
        ->assertJsonPath('data.response_type', 'share_location');
});

it('does not allow another user to respond to a Ping', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createPingCircleFor($owner, $member);
    $ping = createPendingPing($circle, $ownerMembership, $memberMembership);

    Sanctum::actingAs($other);

    $this->postJson('/api/v1/pings/'.$ping->id.'/respond', [
        'response_type' => 'hey',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'PING_FORBIDDEN');
});

it('allows the recipient to dismiss a Ping', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createPingCircleFor($owner, $member);
    $ping = createPendingPing($circle, $ownerMembership, $memberMembership);

    Sanctum::actingAs($member);

    $this->postJson('/api/v1/pings/'.$ping->id.'/dismiss')
        ->assertOk()
        ->assertJsonPath('data.status', 'dismissed');

    $this->assertDatabaseHas('pings', [
        'id' => $ping->id,
        'status' => 'dismissed',
    ]);
});

it('persists expiry when a recipient acts on an expired Ping', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createPingCircleFor($owner, $member);
    $ping = createPendingPing($circle, $ownerMembership, $memberMembership, [
        'expires_at' => now()->subSecond(),
    ]);

    Sanctum::actingAs($member);

    $this->postJson('/api/v1/pings/'.$ping->id.'/respond', [
        'response_type' => 'hey',
    ])
        ->assertStatus(410)
        ->assertJsonPath('code', 'PING_EXPIRED');

    $this->assertDatabaseHas('pings', [
        'id' => $ping->id,
        'status' => 'expired',
    ]);
});
