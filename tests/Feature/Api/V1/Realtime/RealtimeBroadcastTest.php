<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Ping;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Ping\Enums\PingStatus;
use App\Modules\Ping\Events\PingResponded;
use App\Modules\Ping\Events\PingSent;
use App\Modules\Presence\Actions\UpdatePresenceAction;
use App\Modules\Realtime\Broadcasts\CirclePresenceUpdatedBroadcast;
use App\Modules\Realtime\Broadcasts\PingReceivedBroadcast;
use App\Modules\Realtime\Broadcasts\PingRespondedBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function createRealtimeCircle(User $owner, User $member, LocationMode $memberLocationMode = LocationMode::Precise): array
{
    $circle = Circle::query()->create([
        'created_by' => $owner->id,
        'name' => 'Realtime Circle',
        'type' => 'standard',
    ]);

    $ownerMembership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $owner->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Precise,
        'joined_at' => now(),
    ]);

    $memberMembership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => $memberLocationMode,
        'joined_at' => now()->addSecond(),
    ]);

    return [$circle, $ownerMembership, $memberMembership];
}

it('bridges a sent Ping into a private recipient realtime broadcast', function (): void {
    Event::fake([PingReceivedBroadcast::class]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createRealtimeCircle($owner, $member);

    $ping = Ping::query()->create([
        'circle_id' => $circle->id,
        'sender_membership_id' => $ownerMembership->id,
        'recipient_membership_id' => $memberMembership->id,
        'status' => PingStatus::Pending,
        'expires_at' => now()->addMinutes(2),
    ]);

    PingSent::dispatch($ping);

    Event::assertDispatched(
        PingReceivedBroadcast::class,
        fn (PingReceivedBroadcast $event): bool => $event->ping->is($ping)
            && $event->broadcastAs() === 'ping.received'
            && $event->broadcastOn()->name === 'private-users.'.$member->id,
    );
});

it('bridges a Ping response back to the original sender', function (): void {
    Event::fake([PingRespondedBroadcast::class]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, $ownerMembership, $memberMembership] = createRealtimeCircle($owner, $member);

    $ping = Ping::query()->create([
        'circle_id' => $circle->id,
        'sender_membership_id' => $ownerMembership->id,
        'recipient_membership_id' => $memberMembership->id,
        'status' => PingStatus::Responded,
        'response_type' => 'hey',
        'expires_at' => now()->addMinutes(2),
        'responded_at' => now(),
    ]);

    PingResponded::dispatch($ping);

    Event::assertDispatched(
        PingRespondedBroadcast::class,
        fn (PingRespondedBroadcast $event): bool => $event->ping->is($ping)
            && $event->broadcastAs() === 'ping.responded'
            && $event->broadcastOn()->name === 'private-users.'.$owner->id,
    );
});

it('broadcasts privacy-filtered presence to each Circle channel', function (): void {
    Event::fake([CirclePresenceUpdatedBroadcast::class]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $memberMembership] = createRealtimeCircle(
        $owner,
        $member,
        LocationMode::Approximate,
    );

    app(UpdatePresenceAction::class)->handle($member, [
        'status' => 'online',
        'latitude' => 31.5204567,
        'longitude' => 74.3587123,
        'accuracy_meters' => 4.5,
        'movement_type' => 'walking',
    ]);

    Event::assertDispatched(
        CirclePresenceUpdatedBroadcast::class,
        function (CirclePresenceUpdatedBroadcast $event) use ($circle, $member, $memberMembership): bool {
            return $event->circleId === $circle->id
                && $event->membershipId === $memberMembership->id
                && $event->userId === $member->id
                && $event->presence['location']['mode'] === 'approximate'
                && $event->presence['location']['latitude'] === 31.52
                && $event->presence['location']['longitude'] === 74.36
                && $event->presence['location']['accuracy_meters'] === null
                && $event->broadcastAs() === 'presence.updated'
                && $event->broadcastOn()->name === 'private-circles.'.$circle->id;
        },
    );
});

it('broadcasts Ghost Mode without exposing old location metadata', function (): void {
    Event::fake([CirclePresenceUpdatedBroadcast::class]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $memberMembership] = createRealtimeCircle(
        $owner,
        $member,
        LocationMode::Ghost,
    );

    app(UpdatePresenceAction::class)->handle($member, [
        'status' => 'online',
        'latitude' => 31.5204567,
        'longitude' => 74.3587123,
        'accuracy_meters' => 4.5,
        'movement_type' => 'walking',
    ]);

    Event::assertDispatched(
        CirclePresenceUpdatedBroadcast::class,
        function (CirclePresenceUpdatedBroadcast $event) use ($circle, $memberMembership): bool {
            return $event->circleId === $circle->id
                && $event->membershipId === $memberMembership->id
                && $event->presence['status'] === 'ghost'
                && $event->presence['location']['mode'] === 'ghost'
                && $event->presence['location']['latitude'] === null
                && $event->presence['location']['longitude'] === null
                && $event->presence['location']['updated_at'] === null
                && $event->presence['movement_type'] === null
                && $event->presence['last_seen_at'] === null;
        },
    );
});
