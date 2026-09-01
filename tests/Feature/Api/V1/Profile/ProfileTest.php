<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication to read a profile', function (): void {
    $this->getJson('/api/v1/profile')->assertUnauthorized();
});

it('returns the authenticated user profile', function (): void {
    $user = User::factory()->create([
        'name' => 'Kamran',
        'timezone' => 'UTC',
        'locale' => 'en',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Kamran')
        ->assertJsonPath('data.timezone', 'UTC')
        ->assertJsonPath('data.locale', 'en');
});

it('updates the authenticated user profile', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/profile', [
        'name' => 'Orbit User',
        'timezone' => 'Asia/Karachi',
        'locale' => 'en-PK',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Profile updated successfully.')
        ->assertJsonPath('data.name', 'Orbit User')
        ->assertJsonPath('data.timezone', 'Asia/Karachi')
        ->assertJsonPath('data.locale', 'en-PK');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Orbit User',
        'timezone' => 'Asia/Karachi',
        'locale' => 'en-PK',
    ]);
});
