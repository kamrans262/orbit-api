<?php

declare(strict_types=1);

use App\Models\ActivityEvent;
use App\Models\ActivityHiddenEvent;
use App\Models\ActivityReport;
use App\Models\Moment;
use App\Models\User;
use App\Modules\Activity\Actions\RecordActivityEventAction;
use App\Modules\Activity\Enums\ActivityEventType;
use App\Modules\Activity\Events\ActivityItemCreated;
use App\Modules\Activity\Events\ActivityItemRemoved;
use App\Modules\Activity\Http\Middleware\TrackCircleMembershipChanges;
use App\Modules\Activity\Listeners\RecordMomentPublishedActivity;
use App\Modules\Activity\Listeners\RecordSosActivatedActivity;
use App\Modules\Activity\Services\ActivityFeedService;
use App\Modules\Moments\Events\MomentPublished;
use App\Modules\Sos\Events\SosActivated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake([ActivityItemCreated::class, ActivityItemRemoved::class]);
});

function createActivityCircle($test, User $owner): string
{
    Sanctum::actingAs($owner);

    $response = $test->postJson('/api/v1/circles', [
        'name' => 'Activity Circle',
    ])->assertCreated();

    $circleId = data_get($response->json(), 'data.id')
        ?? data_get($response->json(), 'data.circle.id')
        ?? data_get($response->json(), 'id');

    expect($circleId)->not->toBeNull();

    ActivityEvent::query()->delete();
    Event::fake([ActivityItemCreated::class, ActivityItemRemoved::class]);

    return (string) $circleId;
}

function insertActivityMember(string $circleId, User $user, string $role = 'member'): void
{
    $columns = array_flip(Schema::getColumnListing('circle_members'));

    $row = [
        'circle_id' => $circleId,
        'user_id' => $user->getKey(),
        'role' => $role,
        'location_mode' => 'precise',
        'location_fidelity' => 'precise',
        'moment_access' => 'full',
        'can_view_moments' => true,
        'ping_permission' => 'anyone',
        'can_ping' => true,
        'message_permission' => 'full',
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ];

    if (isset($columns['id']) && Schema::getColumnType('circle_members', 'id') !== 'integer') {
        $row['id'] = (string) Str::uuid7();
    }

    DB::table('circle_members')->insert(array_intersect_key($row, $columns));
}

function createActivityEvent(
    string $circleId,
    User $actor,
    ActivityEventType $type = ActivityEventType::MomentPublished,
    ?string $eventKey = null,
    ?DateTimeInterface $occurredAt = null,
): ActivityEvent {
    return ActivityEvent::query()->create([
        'circle_id' => $circleId,
        'actor_user_id' => $actor->getKey(),
        'event_type' => $type->value,
        'source_type' => 'test',
        'source_id' => (string) Str::uuid7(),
        'event_key' => $eventKey ?? 'test:'.Str::uuid7(),
        'payload' => ['safe' => true],
        'occurred_at' => $occurredAt ?? now(),
    ]);
}

test('it requires authentication to read the activity feed', function (): void {
    $this->getJson('/api/v1/activity/feed')->assertUnauthorized();
});

test('it lists only activity from current shared circles in newest first order', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $visibleCircle = createActivityCircle($this, $user);
    $hiddenCircle = createActivityCircle($this, $other);

    $older = createActivityEvent($visibleCircle, $user, occurredAt: now()->subMinute());
    $newer = createActivityEvent($visibleCircle, $user, occurredAt: now());
    createActivityEvent($hiddenCircle, $other);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/activity/feed')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

test('it cursor paginates the activity feed', function (): void {
    $user = User::factory()->create();
    $circleId = createActivityCircle($this, $user);

    createActivityEvent($circleId, $user, occurredAt: now()->subSeconds(2));
    createActivityEvent($circleId, $user, occurredAt: now()->subSecond());
    createActivityEvent($circleId, $user, occurredAt: now());

    Sanctum::actingAs($user);

    $first = $this->getJson('/api/v1/activity/feed?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.has_more', true);

    $cursor = $first->json('meta.next_cursor');

    expect($cursor)->toBeString()->not->toBe('');

    $this->getJson('/api/v1/activity/feed?limit=2&cursor='.urlencode($cursor))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.has_more', false);
});

test('it hides an activity item only for the requesting user and hide is idempotent', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $circleId = createActivityCircle($this, $owner);

    insertActivityMember($circleId, $member);

    $event = createActivityEvent($circleId, $owner);

    Sanctum::actingAs($member);

    $this->postJson("/api/v1/activity/{$event->id}/hide")->assertNoContent();
    $this->postJson("/api/v1/activity/{$event->id}/hide")->assertNoContent();

    expect(ActivityHiddenEvent::query()->count())->toBe(1);

    $this->getJson('/api/v1/activity/feed')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/activity/feed')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('it reports a visible activity item idempotently', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $circleId = createActivityCircle($this, $owner);

    insertActivityMember($circleId, $member);

    $event = createActivityEvent($circleId, $owner);

    Sanctum::actingAs($member);

    $payload = [
        'reason' => 'safety',
        'details' => 'Please review this activity card.',
    ];

    $this->postJson("/api/v1/activity/{$event->id}/report", $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending');

    $this->postJson("/api/v1/activity/{$event->id}/report", $payload)
        ->assertStatus(202);

    expect(ActivityReport::query()->count())->toBe(1);
});

test('it does not expose an activity item after circle access is lost', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $circleId = createActivityCircle($this, $owner);

    insertActivityMember($circleId, $member);

    $event = createActivityEvent($circleId, $owner);

    DB::table('circle_members')
        ->where('circle_id', $circleId)
        ->where('user_id', $member->getKey())
        ->delete();

    Sanctum::actingAs($member);

    $this->postJson("/api/v1/activity/{$event->id}/hide")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'activity_item_unavailable');
});

test('it records an activity item idempotently and emits realtime only once', function (): void {
    Event::fake([ActivityItemCreated::class]);

    $user = User::factory()->create();
    $circleId = createActivityCircle($this, $user);
    Event::fake([ActivityItemCreated::class, ActivityItemRemoved::class]);

    $action = app(RecordActivityEventAction::class);

    $action->handle(
        ActivityEventType::MomentPublished,
        $circleId,
        $user->getKey(),
        'moment',
        'moment-1',
        'moment.published:moment-1',
        ['moment_id' => 'moment-1'],
    );

    $action->handle(
        ActivityEventType::MomentPublished,
        $circleId,
        $user->getKey(),
        'moment',
        'moment-1',
        'moment.published:moment-1',
        ['moment_id' => 'moment-1'],
    );

    expect(ActivityEvent::query()->count())->toBe(1);
    Event::assertDispatchedTimes(ActivityItemCreated::class, 1);
});

test('it converts Moment publication into a safe activity item without encrypted content', function (): void {
    $owner = User::factory()->create();
    $circleId = createActivityCircle($this, $owner);

    $moment = new Moment;
    $moment->forceFill([
        'id' => 'moment-test-1',
        'circle_id' => $circleId,
        'author_user_id' => $owner->getKey(),
        'media_type' => 'image',
        'expires_at' => now()->addDay(),
        'ciphertext' => 'must-not-be-copied',
        'key_envelope' => 'must-not-be-copied',
    ]);

    $listener = app(RecordMomentPublishedActivity::class);
    $listener->handle(new MomentPublished($moment));

    $event = ActivityEvent::query()->sole();

    expect($event->event_type)->toBe(ActivityEventType::MomentPublished->value)
        ->and($event->payload)->toHaveKeys(['moment_id', 'media_type', 'expires_at'])
        ->and($event->payload)->not->toHaveKeys(['ciphertext', 'key_envelope']);
});

test('it converts SOS activation into a safe alert activity item without raw location', function (): void {
    Event::fake([ActivityItemCreated::class]);

    $owner = User::factory()->create();
    $circleId = createActivityCircle($this, $owner);

    $listener = app(RecordSosActivatedActivity::class);
    $listener->handle(new SosActivated([
        'channel' => 'orbit.circle.'.$circleId,
        'event_name' => 'sos.activated',
        'payload' => [
            'sos_id' => 'sos-test-1',
            'circle_id' => $circleId,
            'originator_user_id' => $owner->getKey(),
            'activated_at' => now()->toIso8601String(),
            'latitude' => 31.5204,
            'longitude' => 74.3587,
        ],
    ]));

    $event = ActivityEvent::query()->sole();

    expect($event->event_type)->toBe(ActivityEventType::SosActivated->value)
        ->and($event->payload)->toBe(['sos_id' => 'sos-test-1']);
});

test('dashboard preview contains at most three visible items from the last twenty four hours', function (): void {
    $user = User::factory()->create();
    $circleId = createActivityCircle($this, $user);

    createActivityEvent($circleId, $user, occurredAt: now()->subHours(25));

    for ($index = 0; $index < 4; $index++) {
        createActivityEvent($circleId, $user, occurredAt: now()->subMinutes($index));
    }

    $preview = app(ActivityFeedService::class)->dashboardPreview($user);

    expect($preview)->toHaveCount(3);
});

test('removed source activity does not appear in feed', function (): void {
    $user = User::factory()->create();
    $circleId = createActivityCircle($this, $user);

    $event = createActivityEvent($circleId, $user);
    $event->forceFill(['removed_at' => now()])->save();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/activity/feed')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('membership middleware records joins and leaves without patching Circles actions', function (): void {
    Event::fake([ActivityItemCreated::class, ActivityItemRemoved::class]);

    $owner = User::factory()->create();
    $user = User::factory()->create();
    $circleId = createActivityCircle($this, $owner);

    Route::middleware(['auth:sanctum', TrackCircleMembershipChanges::class])
        ->post('/api/v1/testing/activity-membership/join', function () use ($circleId, $user) {
            insertActivityMember($circleId, $user, 'member');

            return response()->json(['ok' => true]);
        });

    Route::middleware(['auth:sanctum', TrackCircleMembershipChanges::class])
        ->delete('/api/v1/testing/activity-membership/leave', function () use ($circleId, $user) {
            DB::table('circle_members')
                ->where('circle_id', $circleId)
                ->where('user_id', $user->getKey())
                ->delete();

            return response()->json(['ok' => true]);
        });

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/testing/activity-membership/join')->assertOk();
    $this->deleteJson('/api/v1/testing/activity-membership/leave')->assertOk();

    expect(ActivityEvent::query()
        ->where('event_type', ActivityEventType::MemberJoined->value)
        ->count())->toBe(1)
        ->and(ActivityEvent::query()
            ->where('event_type', ActivityEventType::MemberLeft->value)
            ->count())->toBe(1);
});

test('membership middleware ignores failed Circle mutations', function (): void {
    Event::fake([ActivityItemCreated::class, ActivityItemRemoved::class]);

    $owner = User::factory()->create();
    $user = User::factory()->create();
    $circleId = createActivityCircle($this, $owner);

    Route::middleware(['auth:sanctum', TrackCircleMembershipChanges::class])
        ->post('/api/v1/testing/activity-membership/failed', function () use ($circleId, $user) {
            insertActivityMember($circleId, $user, 'member');

            return response()->json(['message' => 'failed'], 422);
        });

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/testing/activity-membership/failed')->assertUnprocessable();

    expect(ActivityEvent::query()->count())->toBe(0);
});

test('report validation rejects unsupported reasons and oversized details', function (): void {
    $user = User::factory()->create();
    $circleId = createActivityCircle($this, $user);
    $event = createActivityEvent($circleId, $user);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/activity/{$event->id}/report", [
        'reason' => 'unsupported',
        'details' => str_repeat('x', 501),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['reason', 'details']);
});

test('feed limit is capped at fifty', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/activity/feed?limit=51')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['limit']);
});
