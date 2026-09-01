<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\MediaAsset;
use App\Models\Moment;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Moments\Enums\MomentStatus;
use App\Modules\Realtime\Broadcasts\MomentDeletedBroadcast;
use App\Modules\Realtime\Broadcasts\MomentPublishedBroadcast;
use App\Modules\Realtime\Broadcasts\MomentViewedBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createMomentContext(
    bool $viewerCanView = true,
    LocationMode $viewerLocationMode = LocationMode::Hidden,
    CircleRole $viewerRole = CircleRole::Member,
): array {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $circle = Circle::query()->create([
        'created_by' => $author->id,
        'name' => 'Private Moments',
        'type' => 'standard',
    ]);

    CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $author->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Hidden,
        'can_view_moments' => true,
        'joined_at' => now(),
    ]);

    CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $viewer->id,
        'role' => $viewerRole,
        'location_mode' => $viewerLocationMode,
        'can_view_moments' => $viewerCanView,
        'joined_at' => now()->addSecond(),
    ]);

    $media = MediaAsset::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'uploader_user_id' => $author->id,
        'kind' => 'image',
        'storage_disk' => 'local',
        'storage_path' => 'moments/example.ciphertext',
        'size_bytes' => 100,
        'sha256_ciphertext' => str_repeat('a', 64),
        'status' => 'ready',
        'expires_at' => now()->addDays(30),
    ]);

    return [$author, $viewer, $circle, $media];
}

function createActiveMoment(User $author, Circle $circle, MediaAsset $media): Moment
{
    return Moment::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'author_user_id' => $author->id,
        'media_asset_id' => $media->id,
        'status' => MomentStatus::Active,
        'expires_at' => now()->addHour(),
    ]);
}

it('requires authentication to list Circle Moments', function (): void {
    $this->getJson('/api/v1/circles/'.Str::uuid().'/moments')->assertUnauthorized();
});

it('publishes a private encrypted image Moment and emits realtime metadata', function (): void {
    Event::fake([MomentPublishedBroadcast::class]);

    [$author, , $circle, $media] = createMomentContext();
    Sanctum::actingAs($author);

    $momentId = (string) Str::uuid();

    $this->postJson('/api/v1/circles/'.$circle->id.'/moments', [
        'moment_id' => $momentId,
        'media_asset_id' => $media->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.id', $momentId)
        ->assertJsonPath('data.media.asset_id', $media->id)
        ->assertJsonMissingPath('data.caption')
        ->assertJsonMissingPath('data.storage_path');

    $this->assertDatabaseHas('moments', [
        'id' => $momentId,
        'circle_id' => $circle->id,
        'author_user_id' => $author->id,
        'media_asset_id' => $media->id,
        'status' => 'active',
    ]);

    Event::assertDispatched(MomentPublishedBroadcast::class);
});

it('is idempotent when the client retries the same Moment ID', function (): void {
    [$author, , $circle, $media] = createMomentContext();
    Sanctum::actingAs($author);

    $payload = [
        'moment_id' => (string) Str::uuid(),
        'media_asset_id' => $media->id,
    ];

    $this->postJson('/api/v1/circles/'.$circle->id.'/moments', $payload)->assertCreated();
    $this->postJson('/api/v1/circles/'.$circle->id.'/moments', $payload)->assertCreated();

    expect(Moment::query()->whereKey($payload['moment_id'])->count())->toBe(1);
});

it('rejects media that is not an owned ready image or video in the same Circle', function (): void {
    [$author, $viewer, $circle] = createMomentContext();

    $foreignMedia = MediaAsset::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'uploader_user_id' => $viewer->id,
        'kind' => 'image',
        'storage_disk' => 'local',
        'storage_path' => 'moments/foreign.ciphertext',
        'size_bytes' => 100,
        'sha256_ciphertext' => str_repeat('b', 64),
        'status' => 'ready',
    ]);

    Sanctum::actingAs($author);

    $this->postJson('/api/v1/circles/'.$circle->id.'/moments', [
        'moment_id' => (string) Str::uuid(),
        'media_asset_id' => $foreignMedia->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'MOMENT_INVALID_MEDIA');
});

it('does not allow a restricted Circle member to publish', function (): void {
    [$author, $viewer, $circle] = createMomentContext(
        viewerRole: CircleRole::Restricted,
    );

    $viewerMedia = MediaAsset::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'uploader_user_id' => $viewer->id,
        'kind' => 'image',
        'storage_disk' => 'local',
        'storage_path' => 'moments/restricted.ciphertext',
        'size_bytes' => 100,
        'sha256_ciphertext' => str_repeat('c', 64),
        'status' => 'ready',
    ]);

    Sanctum::actingAs($viewer);

    $this->postJson('/api/v1/circles/'.$circle->id.'/moments', [
        'moment_id' => (string) Str::uuid(),
        'media_asset_id' => $viewerMedia->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'MOMENT_PUBLISHING_RESTRICTED');
});

it('lists only active unexpired Moments and respects can_view_moments', function (): void {
    [$author, $viewer, $circle, $media] = createMomentContext();
    $active = createActiveMoment($author, $circle, $media);

    Moment::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'author_user_id' => $author->id,
        'media_asset_id' => $media->id,
        'status' => MomentStatus::Expired,
        'expires_at' => now()->subMinute(),
    ]);

    Sanctum::actingAs($viewer);

    $this->getJson('/api/v1/circles/'.$circle->id.'/moments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);

    CircleMember::query()
        ->where('circle_id', $circle->id)
        ->where('user_id', $viewer->id)
        ->update(['can_view_moments' => false]);

    $this->getJson('/api/v1/circles/'.$circle->id.'/moments')
        ->assertForbidden()
        ->assertJsonPath('code', 'MOMENT_VIEWING_DISABLED');
});

it('records a view idempotently', function (): void {
    [$author, $viewer, $circle, $media] = createMomentContext();
    $moment = createActiveMoment($author, $circle, $media);

    Sanctum::actingAs($viewer);

    $this->postJson('/api/v1/moments/'.$moment->id.'/view')
        ->assertOk()
        ->assertJsonPath('data.recorded', true)
        ->assertJsonPath('data.anonymous', false);

    $this->postJson('/api/v1/moments/'.$moment->id.'/view')
        ->assertOk()
        ->assertJsonPath('data.recorded', false);

    $this->assertDatabaseCount('moment_views', 1);
});

it('hides Ghost Mode viewer identity from the author and realtime event', function (): void {
    Event::fake([MomentViewedBroadcast::class]);

    [$author, $viewer, $circle, $media] = createMomentContext(
        viewerLocationMode: LocationMode::Ghost,
    );
    $moment = createActiveMoment($author, $circle, $media);

    Sanctum::actingAs($viewer);

    $this->postJson('/api/v1/moments/'.$moment->id.'/view')
        ->assertOk()
        ->assertJsonPath('data.anonymous', true);

    Event::assertDispatched(
        MomentViewedBroadcast::class,
        fn (MomentViewedBroadcast $event): bool => $event->momentId === $moment->id
            && $event->viewerUserId === null
            && $event->anonymous,
    );

    Sanctum::actingAs($author);

    $this->getJson('/api/v1/moments/'.$moment->id.'/viewers')
        ->assertOk()
        ->assertJsonPath('data.total_views', 1)
        ->assertJsonPath('data.anonymous_views', 1)
        ->assertJsonCount(0, 'data.viewers');
});

it('allows only the author to read viewer identities', function (): void {
    [$author, $viewer, $circle, $media] = createMomentContext();
    $moment = createActiveMoment($author, $circle, $media);

    Sanctum::actingAs($viewer);
    $this->postJson('/api/v1/moments/'.$moment->id.'/view')->assertOk();

    $this->getJson('/api/v1/moments/'.$moment->id.'/viewers')
        ->assertForbidden();

    Sanctum::actingAs($author);

    $this->getJson('/api/v1/moments/'.$moment->id.'/viewers')
        ->assertOk()
        ->assertJsonPath('data.viewers.0.user_id', $viewer->id)
        ->assertJsonPath('data.anonymous_views', 0);
});

it('does not count the authors own view', function (): void {
    [$author, , $circle, $media] = createMomentContext();
    $moment = createActiveMoment($author, $circle, $media);
    Sanctum::actingAs($author);

    $this->postJson('/api/v1/moments/'.$moment->id.'/view')
        ->assertOk()
        ->assertJsonPath('data.recorded', false);

    $this->assertDatabaseCount('moment_views', 0);
});

it('returns gone for an expired Moment', function (): void {
    [$author, $viewer, $circle, $media] = createMomentContext();

    $moment = Moment::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'author_user_id' => $author->id,
        'media_asset_id' => $media->id,
        'status' => MomentStatus::Active,
        'expires_at' => now()->subSecond(),
    ]);

    Sanctum::actingAs($viewer);

    $this->postJson('/api/v1/moments/'.$moment->id.'/view')
        ->assertStatus(410)
        ->assertJsonPath('code', 'MOMENT_EXPIRED');

    $this->assertDatabaseHas('moments', [
        'id' => $moment->id,
        'status' => 'expired',
    ]);
});

it('allows only the author to delete a Moment and broadcasts deletion', function (): void {
    Event::fake([MomentDeletedBroadcast::class]);

    [$author, $viewer, $circle, $media] = createMomentContext();
    $moment = createActiveMoment($author, $circle, $media);

    Sanctum::actingAs($viewer);
    $this->deleteJson('/api/v1/moments/'.$moment->id)
        ->assertForbidden();

    Sanctum::actingAs($author);
    $this->deleteJson('/api/v1/moments/'.$moment->id)
        ->assertOk();

    $this->assertDatabaseHas('moments', [
        'id' => $moment->id,
        'status' => 'deleted',
    ]);

    Event::assertDispatched(MomentDeletedBroadcast::class);
});

it('marks expired Moments through the scheduled cleanup command', function (): void {
    [$author, , $circle, $media] = createMomentContext();

    $moment = Moment::query()->create([
        'id' => (string) Str::uuid(),
        'circle_id' => $circle->id,
        'author_user_id' => $author->id,
        'media_asset_id' => $media->id,
        'status' => MomentStatus::Active,
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('orbit:moments:purge-expired')->assertSuccessful();

    $this->assertDatabaseHas('moments', [
        'id' => $moment->id,
        'status' => 'expired',
    ]);
});
