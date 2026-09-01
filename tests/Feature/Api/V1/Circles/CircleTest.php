<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\CircleInvite;
use App\Models\CircleMember;
use App\Models\User;
use App\Modules\Circles\Actions\CreateCircleInviteAction;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createCircleFor(User $user, array $attributes = []): array
{
    $circle = Circle::query()->create(array_merge([
        'created_by' => $user->id,
        'name' => 'Family',
        'type' => 'standard',
    ], $attributes));

    $membership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $user->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    return [$circle, $membership];
}

it('requires authentication to list circles', function (): void {
    $this->getJson('/api/v1/circles')->assertUnauthorized();
});

it('creates a circle and makes the creator the only owner', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/circles', [
        'name' => 'Family',
        'description' => 'Our private family Circle',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Family')
        ->assertJsonPath('data.my_role', 'owner')
        ->assertJsonPath('data.member_count', 1);

    $circleId = $response->json('data.id');

    $this->assertDatabaseHas('circle_members', [
        'circle_id' => $circleId,
        'user_id' => $user->id,
        'role' => 'owner',
        'location_mode' => 'hidden',
    ]);

    expect(CircleMember::query()->where('circle_id', $circleId)->where('role', 'owner')->count())->toBe(1);
});

it('lists only circles the authenticated user belongs to', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    [$mine] = createCircleFor($user, ['name' => 'Mine']);
    createCircleFor($otherUser, ['name' => 'Not Mine']);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/circles')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.name', 'Mine');
});

it('hides a circle from non-members', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    [$circle] = createCircleFor($owner);

    Sanctum::actingAs($otherUser);

    $this->getJson('/api/v1/circles/'.$circle->id)
        ->assertNotFound()
        ->assertJsonPath('code', 'CIRCLE_NOT_FOUND');
});

it('allows owner to create an invite and stores only its hash', function (): void {
    $owner = User::factory()->create();
    [$circle] = createCircleFor($owner);
    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/v1/circles/'.$circle->id.'/invites', [
        'max_uses' => 2,
        'expires_in_minutes' => 60,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.max_uses', 2);

    $code = $response->json('data.code');
    $invite = CircleInvite::query()->firstOrFail();

    expect($code)->toBeString()->not->toBe('')
        ->and($invite->code_hash)->toBe(CreateCircleInviteAction::hashCode($code))
        ->and($invite->code_hash)->not->toBe($code);
});

it('joins a circle using a valid invite without creating duplicate memberships', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createCircleFor($owner);

    $code = 'JOINCODE99';
    $invite = CircleInvite::query()->create([
        'circle_id' => $circle->id,
        'created_by' => $owner->id,
        'code_hash' => CreateCircleInviteAction::hashCode($code),
        'max_uses' => 5,
        'uses_count' => 0,
        'expires_at' => now()->addHour(),
    ]);

    Sanctum::actingAs($member);

    $this->postJson('/api/v1/circles/join', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.id', $circle->id)
        ->assertJsonPath('data.my_role', 'member');

    $this->postJson('/api/v1/circles/join', ['code' => $code])->assertOk();

    expect(CircleMember::query()->where('circle_id', $circle->id)->where('user_id', $member->id)->count())->toBe(1)
        ->and($invite->fresh()->uses_count)->toBe(1);
});

it('rejects an expired invite', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createCircleFor($owner);
    $code = 'EXPIRED999';

    CircleInvite::query()->create([
        'circle_id' => $circle->id,
        'created_by' => $owner->id,
        'code_hash' => CreateCircleInviteAction::hashCode($code),
        'max_uses' => 1,
        'uses_count' => 0,
        'expires_at' => now()->subMinute(),
    ]);

    Sanctum::actingAs($member);

    $this->postJson('/api/v1/circles/join', ['code' => $code])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_OR_EXPIRED_INVITE');
});

it('allows a member to change only their own Circle privacy settings', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createCircleFor($owner);
    $membership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    Sanctum::actingAs($member);

    $this->patchJson('/api/v1/circles/'.$circle->id.'/members/'.$membership->id, [
        'location_mode' => 'precise',
        'can_ping' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.location_mode', 'precise')
        ->assertJsonPath('data.can_ping', false);

    expect($membership->fresh()->location_mode)->toBe(LocationMode::Precise);
});

it('allows the owner to promote a member to admin', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createCircleFor($owner);
    $membership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson('/api/v1/circles/'.$circle->id.'/members/'.$membership->id, [
        'role' => 'admin',
    ])
        ->assertOk()
        ->assertJsonPath('data.role', 'admin');

    expect($membership->fresh()->role)->toBe(CircleRole::Admin);
});

it('does not allow an owner to leave their circle', function (): void {
    $owner = User::factory()->create();
    [$circle] = createCircleFor($owner);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/circles/'.$circle->id.'/leave')
        ->assertStatus(409)
        ->assertJsonPath('code', 'OWNER_CANNOT_LEAVE');
});

it('allows a regular member to leave a circle', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createCircleFor($owner);
    $membership = CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    Sanctum::actingAs($member);

    $this->postJson('/api/v1/circles/'.$circle->id.'/leave')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('circle_members', ['id' => $membership->id]);
});

it('allows only the owner to archive a circle', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$circle] = createCircleFor($owner);
    CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    Sanctum::actingAs($member);
    $this->deleteJson('/api/v1/circles/'.$circle->id)
        ->assertForbidden()
        ->assertJsonPath('code', 'CIRCLE_FORBIDDEN');

    Sanctum::actingAs($owner);
    $this->deleteJson('/api/v1/circles/'.$circle->id)
        ->assertOk()
        ->assertJsonPath('data.is_archived', true);

    expect($circle->fresh()->archived_at)->not->toBeNull();
});
