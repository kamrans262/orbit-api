<?php

declare(strict_types=1);

use App\Models\SosEvent;
use App\Models\SosNotificationOutbox;
use App\Models\User;
use App\Modules\Sos\Events\SosActivated;
use App\Modules\Sos\Events\SosEscalated;
use App\Modules\Sos\Events\SosLocationUpdated;
use App\Modules\Sos\Events\SosResolved;
use App\Modules\Sos\Events\SosResponderEngaged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake([
        SosActivated::class,
        SosResponderEngaged::class,
        SosLocationUpdated::class,
        SosEscalated::class,
        SosResolved::class,
    ]);
});

it('requires authentication to activate SOS', function (): void {
    $this->postJson('/api/v1/sos/activate', [
        'id' => (string) Str::uuid(),
        'circle_id' => (string) Str::uuid(),
    ])->assertUnauthorized();
});

it('activates SOS for a Circle member and creates responder and notification records', function (): void {
    [$owner, $member, $circleId] = createSosFixture($this);
    Sanctum::actingAs($owner);
    $sosId = (string) Str::uuid();

    $this->postJson('/api/v1/sos/activate', [
        'id' => $sosId,
        'circle_id' => $circleId,
        'latitude' => 31.5204567,
        'longitude' => 74.3587123,
        'location_accuracy_m' => 8.5,
    ])->assertCreated()
        ->assertJsonPath('data.id', $sosId)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.escalation_stage', 0)
        ->assertJsonPath('meta.idempotent_replay', false);

    $this->assertDatabaseHas('sos_responders', [
        'sos_event_id' => $sosId,
        'user_id' => $member->id,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('sos_notification_outbox', [
        'sos_event_id' => $sosId,
        'target_user_id' => $member->id,
        'kind' => 'sos.activated',
        'priority' => 'highest',
        'status' => 'pending',
    ]);
    Event::assertDispatched(SosActivated::class, 1);
});

it('is idempotent when activation is retried with the same client SOS ID', function (): void {
    [$owner, , $circleId] = createSosFixture($this);
    Sanctum::actingAs($owner);
    $sosId = (string) Str::uuid();
    $payload = ['id' => $sosId, 'circle_id' => $circleId];

    $this->postJson('/api/v1/sos/activate', $payload)->assertCreated();
    $this->postJson('/api/v1/sos/activate', $payload)
        ->assertOk()
        ->assertJsonPath('meta.idempotent_replay', true);

    expect(SosEvent::query()->whereKey($sosId)->count())->toBe(1)
        ->and(SosNotificationOutbox::query()->where('sos_event_id', $sosId)->count())->toBe(1);
});

it('does not allow a non-member to activate SOS in a Circle', function (): void {
    [$owner, , $circleId] = createSosFixture($this);
    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider);

    $this->postJson('/api/v1/sos/activate', [
        'id' => (string) Str::uuid(),
        'circle_id' => $circleId,
    ])->assertNotFound()
        ->assertJsonPath('error.code', 'sos_circle_unavailable');
});

it('rate limits the fourth SOS activation inside sixty minutes', function (): void {
    [$owner, , $circleId] = createSosFixture($this);
    Sanctum::actingAs($owner);

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/v1/sos/activate', [
            'id' => (string) Str::uuid(),
            'circle_id' => $circleId,
        ])->assertCreated();
    }

    $this->postJson('/api/v1/sos/activate', [
        'id' => (string) Str::uuid(),
        'circle_id' => $circleId,
    ])->assertStatus(429)
        ->assertJsonPath('error.code', 'sos_activation_rate_limited')
        ->assertJsonPath('error.context.assistance_confirmation_required', true);
});

it('lets a Circle member recover the active SOS state', function (): void {
    [$owner, $member, $circleId] = createSosFixture($this);
    $sosId = activateSos($this, $owner, $circleId);
    Sanctum::actingAs($member);

    $this->getJson('/api/v1/sos/'.$sosId)
        ->assertOk()
        ->assertJsonPath('data.id', $sosId)
        ->assertJsonPath('data.status', 'active');
});

it('lets a responder engage idempotently', function (): void {
    [$owner, $member, $circleId] = createSosFixture($this);
    $sosId = activateSos($this, $owner, $circleId);
    Sanctum::actingAs($member);

    $this->postJson('/api/v1/sos/'.$sosId.'/respond', ['status' => 'engaged'])
        ->assertOk()
        ->assertJsonPath('data.responders.0.status', 'engaged');
    $this->postJson('/api/v1/sos/'.$sosId.'/respond', ['status' => 'engaged'])->assertOk();

    $this->assertDatabaseCount('sos_responders', 1);
    Event::assertDispatched(SosResponderEngaged::class, 1);
});

it('does not let the originator respond to their own SOS', function (): void {
    [$owner, , $circleId] = createSosFixture($this);
    $sosId = activateSos($this, $owner, $circleId);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/sos/'.$sosId.'/respond', ['status' => 'engaged'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'sos_originator_cannot_respond');
});

it('allows only originator or engaged responders to publish SOS location', function (): void {
    [$owner, $member, $circleId] = createSosFixture($this);
    $sosId = activateSos($this, $owner, $circleId);
    Sanctum::actingAs($member);

    $this->putJson('/api/v1/sos/'.$sosId.'/location', [
        'latitude' => 31.5,
        'longitude' => 74.3,
    ])->assertForbidden()
        ->assertJsonPath('error.code', 'sos_location_forbidden');

    $this->postJson('/api/v1/sos/'.$sosId.'/respond', ['status' => 'engaged'])->assertOk();
    $this->putJson('/api/v1/sos/'.$sosId.'/location', [
        'latitude' => 31.5001,
        'longitude' => 74.3001,
        'accuracy_m' => 5,
    ])->assertOk()
        ->assertJsonPath('data.accepted', true);

    $this->assertDatabaseHas('sos_responders', [
        'sos_event_id' => $sosId,
        'user_id' => $member->id,
        'status' => 'engaged',
    ]);
    Event::assertDispatched(SosLocationUpdated::class, 1);
});

it('allows only the originator to resolve SOS and resolution is idempotent', function (): void {
    [$owner, $member, $circleId] = createSosFixture($this);
    $sosId = activateSos($this, $owner, $circleId);
    Sanctum::actingAs($member);

    $this->postJson('/api/v1/sos/'.$sosId.'/resolve', ['reason' => 'help_arrived'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'sos_resolve_forbidden');

    Sanctum::actingAs($owner);
    $this->postJson('/api/v1/sos/'.$sosId.'/resolve', ['reason' => 'safe'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolution_reason', 'safe');
    $this->postJson('/api/v1/sos/'.$sosId.'/resolve', ['reason' => 'safe'])->assertOk();

    Event::assertDispatched(SosResolved::class, 1);
});

it('advances server authoritative escalation stages and stops after a responder engages', function (): void {
    [$owner, $member, $circleId] = createSosFixture($this);
    $sosId = activateSos($this, $owner, $circleId);
    SosEvent::query()->whereKey($sosId)->update(['activated_at' => now()->subSeconds(190)]);

    Artisan::call('orbit:sos:escalate');

    $this->assertDatabaseHas('sos_events', ['id' => $sosId, 'escalation_stage' => 2]);
    $this->assertDatabaseHas('sos_escalations', ['sos_event_id' => $sosId, 'stage' => 1]);
    $this->assertDatabaseHas('sos_escalations', [
        'sos_event_id' => $sosId,
        'stage' => 2,
        'status' => 'pending_provider',
    ]);

    Sanctum::actingAs($member);
    $this->postJson('/api/v1/sos/'.$sosId.'/respond', ['status' => 'engaged'])->assertOk();
    SosEvent::query()->whereKey($sosId)->update(['activated_at' => now()->subSeconds(400)]);
    Artisan::call('orbit:sos:escalate');

    $this->assertDatabaseMissing('sos_escalations', ['sos_event_id' => $sosId, 'stage' => 3]);
});

it('attaches only an opaque encrypted recording reference and clears it after retention', function (): void {
    [$owner, , $circleId] = createSosFixture($this);
    $sosId = activateSos($this, $owner, $circleId);
    Sanctum::actingAs($owner);

    $this->putJson('/api/v1/sos/'.$sosId.'/recording', [
        'recording_ref' => 'media:sos:01HXCRYPTEDREF',
    ])->assertOk()
        ->assertJsonPath('data.recording_ref', 'media:sos:01HXCRYPTEDREF');

    SosEvent::query()->whereKey($sosId)->update(['recording_expires_at' => now()->subSecond()]);
    Artisan::call('orbit:sos:purge-expired-recordings');

    $this->assertDatabaseHas('sos_events', [
        'id' => $sosId,
        'recording_ref' => null,
        'recording_expires_at' => null,
    ]);
});

function activateSos($test, User $owner, string $circleId): string
{
    Sanctum::actingAs($owner);
    $sosId = (string) Str::uuid();
    $test->postJson('/api/v1/sos/activate', [
        'id' => $sosId,
        'circle_id' => $circleId,
    ])->assertCreated();

    return $sosId;
}

function createSosFixture($test): array
{
    $owner = User::factory()->create();
    Sanctum::actingAs($owner);
    $response = $test->postJson('/api/v1/circles', ['name' => 'Safety Circle']);
    $response->assertCreated();
    $circleId = data_get($response->json(), 'data.id')
        ?? data_get($response->json(), 'data.circle.id')
        ?? data_get($response->json(), 'id');

    expect($circleId)->not->toBeNull();

    $member = User::factory()->create();
    insertCircleMemberForSos((string) $circleId, $member->id);

    return [$owner, $member, (string) $circleId];
}

function insertCircleMemberForSos(string $circleId, int $userId): void
{
    $columns = array_flip(Schema::getColumnListing('circle_members'));
    $row = [
        'circle_id' => $circleId,
        'user_id' => $userId,
    ];

    $defaults = [
        'role' => 'member',
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

    foreach ($defaults as $column => $value) {
        if (isset($columns[$column])) {
            $row[$column] = $value;
        }
    }

    if (isset($columns['id'])) {
        $idType = Schema::getColumnType('circle_members', 'id');

        if (! in_array($idType, ['integer', 'bigint', 'smallint', 'mediumint', 'tinyint'], true)) {
            $row['id'] = (string) Str::uuid7();
        }
    }

    DB::table('circle_members')->insert($row);
}
