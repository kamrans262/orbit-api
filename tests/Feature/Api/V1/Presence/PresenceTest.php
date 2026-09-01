<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Device;
use App\Models\PresenceState;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Presence\Enums\MovementType;
use App\Modules\Presence\Enums\NetworkType;
use App\Modules\Presence\Enums\PresenceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createPresenceCircleFor(User $owner, User $member, LocationMode $mode = LocationMode::Precise): array
{
    $circle = Circle::query()->create([
        'created_by' => $owner->id,
        'name' => 'Presence Circle',
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
        'location_mode' => $mode,
        'joined_at' => now()->addSecond(),
    ]);

    return [$circle, $ownerMembership, $memberMembership];
}

function createPresenceFor(User $user, array $attributes = []): PresenceState
{
    return PresenceState::query()->create(array_merge([
        'user_id' => $user->id,
        'status' => PresenceStatus::Online,
        'latitude' => 31.5204567,
        'longitude' => 74.3587123,
        'accuracy_meters' => 8.5,
        'battery_level' => 72,
        'is_charging' => false,
        'network_type' => NetworkType::Wifi,
        'movement_type' => MovementType::Walking,
        'location_updated_at' => now(),
        'reported_at' => now(),
    ], $attributes));
}

it('requires authentication to update presence', function (): void {
    $this->putJson('/api/v1/presence', [
        'status' => 'online',
    ])->assertUnauthorized();
});

it('updates the authenticated users current presence and active device last seen time', function (): void {
    $user = User::factory()->create();
    $device = Device::query()->create([
        'user_id' => $user->id,
        'client_device_id' => 'presence-device-1',
        'platform' => 'android',
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/v1/presence', [
        'device_id' => $device->id,
        'status' => 'online',
        'latitude' => 31.5204567,
        'longitude' => 74.3587123,
        'accuracy_meters' => 7.4,
        'battery_level' => 81,
        'is_charging' => true,
        'network_type' => 'wifi',
        'movement_type' => 'walking',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'online')
        ->assertJsonPath('data.location.mode', 'precise')
        ->assertJsonPath('data.location.latitude', 31.5204567)
        ->assertJsonPath('data.battery.level', 81)
        ->assertJsonPath('data.device_id', $device->id);

    $this->assertDatabaseHas('presence_states', [
        'user_id' => $user->id,
        'device_id' => $device->id,
        'battery_level' => 81,
    ]);

    expect($device->fresh()->last_seen_at)->not->toBeNull();
});

it('rejects another users device when updating presence', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $device = Device::query()->create([
        'user_id' => $other->id,
        'client_device_id' => 'other-device',
        'platform' => 'ios',
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/v1/presence', [
        'device_id' => $device->id,
        'status' => 'online',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'PRESENCE_DEVICE_INVALID');
});

it('returns the owners raw presence from the me endpoint', function (): void {
    $user = User::factory()->create();
    createPresenceFor($user);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/presence/me')
        ->assertOk()
        ->assertJsonPath('data.global_ghost_mode', false)
        ->assertJsonPath('data.location.mode', 'precise')
        ->assertJsonPath('data.location.latitude', 31.5204567)
        ->assertJsonPath('data.location.longitude', 74.3587123);
});

it('returns precise location only when the target member permits precise location', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $membership] = createPresenceCircleFor($owner, $member, LocationMode::Precise);
    createPresenceFor($member);
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/circles/'.$circle->id.'/members/'.$membership->id.'/presence')
        ->assertOk()
        ->assertJsonPath('data.presence.location.mode', 'precise')
        ->assertJsonPath('data.presence.location.latitude', 31.5204567)
        ->assertJsonPath('data.presence.location.longitude', 74.3587123)
        ->assertJsonPath('data.presence.location.accuracy_meters', 8.5);
});

it('coarsens coordinates for approximate Circle location sharing', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $membership] = createPresenceCircleFor($owner, $member, LocationMode::Approximate);
    createPresenceFor($member);
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/circles/'.$circle->id.'/members/'.$membership->id.'/presence')
        ->assertOk()
        ->assertJsonPath('data.presence.location.mode', 'approximate')
        ->assertJsonPath('data.presence.location.latitude', 31.52)
        ->assertJsonPath('data.presence.location.longitude', 74.36)
        ->assertJsonPath('data.presence.location.accuracy_meters', null);
});

it('never exposes coordinates or movement for hidden Circle location sharing', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $membership] = createPresenceCircleFor($owner, $member, LocationMode::Hidden);
    createPresenceFor($member);
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/circles/'.$circle->id.'/members/'.$membership->id.'/presence')
        ->assertOk()
        ->assertJsonPath('data.presence.status', 'online')
        ->assertJsonPath('data.presence.location.mode', 'hidden')
        ->assertJsonPath('data.presence.location.latitude', null)
        ->assertJsonPath('data.presence.location.longitude', null)
        ->assertJsonPath('data.presence.movement_type', null)
        ->assertJsonPath('data.presence.battery.level', 72);
});

it('never exposes last known coordinates or metadata in Circle Ghost Mode', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $membership] = createPresenceCircleFor($owner, $member, LocationMode::Ghost);
    createPresenceFor($member);
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/circles/'.$circle->id.'/members/'.$membership->id.'/presence')
        ->assertOk()
        ->assertJsonPath('data.presence.status', 'ghost')
        ->assertJsonPath('data.presence.location.mode', 'ghost')
        ->assertJsonPath('data.presence.location.latitude', null)
        ->assertJsonPath('data.presence.location.updated_at', null)
        ->assertJsonPath('data.presence.battery.level', null)
        ->assertJsonPath('data.presence.network_type', null)
        ->assertJsonPath('data.presence.last_seen_at', null);
});

it('global Ghost Mode clears server coordinates and overrides every Circle location mode', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $membership] = createPresenceCircleFor($owner, $member, LocationMode::Precise);
    createPresenceFor($member);

    Sanctum::actingAs($member);
    $this->patchJson('/api/v1/presence/settings', [
        'global_ghost_mode' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.global_ghost_mode', true)
        ->assertJsonPath('data.status', 'ghost');

    $presence = PresenceState::query()->where('user_id', $member->id)->firstOrFail();
    expect($presence->latitude)->toBeNull()
        ->and($presence->longitude)->toBeNull()
        ->and($presence->movement_type)->toBeNull();

    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/circles/'.$circle->id.'/members/'.$membership->id.'/presence')
        ->assertOk()
        ->assertJsonPath('data.presence.status', 'ghost')
        ->assertJsonPath('data.presence.location.mode', 'ghost')
        ->assertJsonPath('data.presence.location.latitude', null);
});

it('applies location privacy independently for the same user in different Circles', function (): void {
    $viewer = User::factory()->create();
    $member = User::factory()->create();
    [$preciseCircle, , $preciseMembership] = createPresenceCircleFor($viewer, $member, LocationMode::Precise);
    [$hiddenCircle, , $hiddenMembership] = createPresenceCircleFor($viewer, $member, LocationMode::Hidden);
    createPresenceFor($member);
    Sanctum::actingAs($viewer);

    $this->getJson('/api/v1/circles/'.$preciseCircle->id.'/members/'.$preciseMembership->id.'/presence')
        ->assertOk()
        ->assertJsonPath('data.presence.location.latitude', 31.5204567);

    $this->getJson('/api/v1/circles/'.$hiddenCircle->id.'/members/'.$hiddenMembership->id.'/presence')
        ->assertOk()
        ->assertJsonPath('data.presence.location.mode', 'hidden')
        ->assertJsonPath('data.presence.location.latitude', null);
});

it('shows stale presence as offline while preserving the latest privacy-permitted location timestamp', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $membership] = createPresenceCircleFor($owner, $member, LocationMode::Precise);
    $presence = createPresenceFor($member);

    DB::table('presence_states')
        ->where('id', $presence->id)
        ->update(['updated_at' => now()->subMinutes(10)]);

    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/circles/'.$circle->id.'/members/'.$membership->id.'/presence')
        ->assertOk()
        ->assertJsonPath('data.presence.status', 'offline')
        ->assertJsonPath('data.presence.location.latitude', 31.5204567);
});

it('returns offline presence for a Circle member who has never reported presence', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle, , $membership] = createPresenceCircleFor($owner, $member, LocationMode::Hidden);
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/circles/'.$circle->id.'/members/'.$membership->id.'/presence')
        ->assertOk()
        ->assertJsonPath('data.presence.status', 'offline')
        ->assertJsonPath('data.presence.location.latitude', null)
        ->assertJsonPath('data.presence.last_seen_at', null);
});

it('lists effective privacy-filtered presence for all Circle members', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createPresenceCircleFor($owner, $member, LocationMode::Approximate);
    createPresenceFor($owner, ['latitude' => 30.1234567, 'longitude' => 70.7654321]);
    createPresenceFor($member);
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/circles/'.$circle->id.'/presence')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.presence.location.mode', 'approximate')
        ->assertJsonPath('data.1.presence.location.latitude', 31.52);
});

it('does not allow a non-member to read Circle presence', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    [$circle] = createPresenceCircleFor($owner, $member);
    Sanctum::actingAs($outsider);

    $this->getJson('/api/v1/circles/'.$circle->id.'/presence')
        ->assertNotFound()
        ->assertJsonPath('code', 'CIRCLE_NOT_FOUND');
});
