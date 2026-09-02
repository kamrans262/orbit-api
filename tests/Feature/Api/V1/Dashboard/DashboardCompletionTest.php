<?php

declare(strict_types=1);

use App\Models\ActivityEvent;
use App\Models\ActivityHiddenEvent;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\PresenceState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function m9UserHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('m9')->plainTextToken];
}

function m9Circle(User $owner, User ...$members): Circle
{
    $circle = Circle::query()->create(['created_by' => $owner->id, 'name' => 'M9 Circle', 'type' => 'standard']);
    CircleMember::query()->create(['circle_id' => $circle->id, 'user_id' => $owner->id, 'role' => 'owner', 'location_mode' => 'precise', 'can_ping' => true, 'can_message' => true, 'can_view_moments' => true, 'activity_visibility' => true, 'joined_at' => now()]);
    foreach ($members as $member) {
        CircleMember::query()->create(['circle_id' => $circle->id, 'user_id' => $member->id, 'role' => 'member', 'location_mode' => 'approximate', 'can_ping' => true, 'can_message' => true, 'can_view_moments' => true, 'activity_visibility' => true, 'joined_at' => now()]);
    }

    return $circle;
}

test('dashboard summary and member recent require consumer authentication', function (): void {
    $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
    $this->getJson('/api/v1/users/1/recent')->assertUnauthorized();
});

test('dashboard summary exposes real circle presence activity and safe operational counts', function (): void {
    $user = User::factory()->create();
    $circle = m9Circle($user);
    PresenceState::query()->create(['user_id' => $user->id, 'status' => 'online', 'reported_at' => now()]);
    ActivityEvent::query()->create(['circle_id' => $circle->id, 'actor_user_id' => $user->id, 'event_type' => 'member.joined', 'source_type' => 'membership', 'source_id' => 'm9', 'event_key' => 'm9-dashboard', 'payload' => [], 'occurred_at' => now()]);

    $this->withHeaders(m9UserHeaders($user))->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.circles.count', 1)
        ->assertJsonPath('data.presence.status', 'online')
        ->assertJsonCount(1, 'data.activity');
});

test('dashboard activity preview remains capped to three safe items', function (): void {
    $user = User::factory()->create();
    $circle = m9Circle($user);
    foreach (range(1, 5) as $i) {
        ActivityEvent::query()->create(['circle_id' => $circle->id, 'actor_user_id' => $user->id, 'event_type' => 'member.joined', 'source_type' => 'membership', 'source_id' => 'm9-'.$i, 'event_key' => 'm9-'.$i, 'payload' => ['safe' => true], 'occurred_at' => now()->subMinutes($i)]);
    }
    $this->withHeaders(m9UserHeaders($user))->getJson('/api/v1/dashboard/summary')->assertOk()->assertJsonCount(3, 'data.activity');
});

test('member recent is hidden from unrelated users', function (): void {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $this->withHeaders(m9UserHeaders($viewer))->getJson('/api/v1/users/'.$target->id.'/recent')->assertNotFound();
});

test('member recent preserves per circle location privacy instead of choosing a universal location', function (): void {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $circle = m9Circle($viewer, $target);
    PresenceState::query()->create(['user_id' => $target->id, 'status' => 'online', 'latitude' => 31.5204, 'longitude' => 74.3587, 'location_updated_at' => now(), 'reported_at' => now()]);

    $this->withHeaders(m9UserHeaders($viewer))->getJson('/api/v1/users/'.$target->id.'/recent')
        ->assertOk()
        ->assertJsonPath('data.presence_by_circle.0.circle.id', $circle->id)
        ->assertJsonPath('data.presence_by_circle.0.presence.location.mode', 'approximate')
        ->assertJsonPath('data.presence_by_circle.0.presence.location.latitude', 31.52);
});

test('member recent respects activity items hidden by the viewing user', function (): void {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $circle = m9Circle($viewer, $target);

    $event = ActivityEvent::query()->create([
        'circle_id' => $circle->id,
        'actor_user_id' => $target->id,
        'event_type' => 'member.joined',
        'source_type' => 'membership',
        'source_id' => 'm9-hidden-member-event',
        'event_key' => 'm9-hidden-member-event',
        'payload' => [],
        'occurred_at' => now(),
    ]);
    ActivityHiddenEvent::query()->create([
        'user_id' => $viewer->id,
        'activity_event_id' => $event->id,
        'hidden_at' => now(),
    ]);

    $this->withHeaders(m9UserHeaders($viewer))
        ->getJson('/api/v1/users/'.$target->id.'/recent')
        ->assertOk()
        ->assertJsonCount(0, 'data.recent_activity');
});

test('member recent never exposes SOS coordinates or recording references', function (): void {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    m9Circle($viewer, $target);
    $response = $this->withHeaders(m9UserHeaders($viewer))->getJson('/api/v1/users/'.$target->id.'/recent')->assertOk();
    expect(json_encode($response->json()))->not->toContain('recording_ref')->not->toContain('last_latitude')->not->toContain('last_longitude');
});

test('all consumer API responses echo a valid request id and preserve a valid caller request id', function (): void {
    $user = User::factory()->create();
    $requestId = 'orbit-test-request-1234';
    $this->withHeaders([...m9UserHeaders($user), 'X-Request-Id' => $requestId])->getJson('/api/v1/dashboard/summary')
        ->assertOk()->assertHeader('X-Request-Id', $requestId);
});
