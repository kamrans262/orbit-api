<?php

declare(strict_types=1);

use App\Models\EmailOtp;
use App\Models\User;
use App\Modules\Auth\Mail\EmailOtpMail;
use App\Modules\Auth\Services\EmailOtpGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requests an email OTP through Laravel Mail without exposing the OTP in the API response', function (): void {
    Mail::fake();

    $this->mock(EmailOtpGenerator::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn('123456');

    $response = $this->postJson('/api/v1/auth/email-otp/request', [
        'email' => 'User@Example.com ',
    ]);

    $response
        ->assertAccepted()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'user@example.com')
        ->assertJsonMissing(['otp' => '123456']);

    $record = EmailOtp::query()->where('email', 'user@example.com')->firstOrFail();

    expect(Hash::check('123456', $record->code_hash))->toBeTrue();

    Mail::assertSent(EmailOtpMail::class, function (EmailOtpMail $mail): bool {
        return $mail->hasTo('user@example.com') && $mail->otp === '123456';
    });
});

it('verifies an email OTP, creates a user, and returns a Sanctum token', function (): void {
    EmailOtp::query()->create([
        'email' => 'user@example.com',
        'code_hash' => Hash::make('123456'),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/auth/email-otp/verify', [
        'email' => 'user@example.com',
        'otp' => '123456',
        'device_name' => 'Test Device',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'user@example.com')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'expires_at',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'user@example.com',
    ]);

    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('persists incorrect OTP attempts', function (): void {
    $record = EmailOtp::query()->create([
        'email' => 'user@example.com',
        'code_hash' => Hash::make('123456'),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/v1/auth/email-otp/verify', [
        'email' => 'user@example.com',
        'otp' => '999999',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'INVALID_OTP');

    expect($record->fresh()->attempts)->toBe(1);
});

it('locks an OTP after the maximum number of incorrect attempts', function (): void {
    $record = EmailOtp::query()->create([
        'email' => 'user@example.com',
        'code_hash' => Hash::make('123456'),
        'attempts' => 4,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/v1/auth/email-otp/verify', [
        'email' => 'user@example.com',
        'otp' => '999999',
    ])
        ->assertStatus(429)
        ->assertJsonPath('code', 'OTP_ATTEMPTS_EXCEEDED');

    $record->refresh();

    expect($record->attempts)->toBe(5)
        ->and($record->used_at)->not->toBeNull();
});

it('marks an expired OTP as used', function (): void {
    $record = EmailOtp::query()->create([
        'email' => 'user@example.com',
        'code_hash' => Hash::make('123456'),
        'attempts' => 0,
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/v1/auth/email-otp/verify', [
        'email' => 'user@example.com',
        'otp' => '123456',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'OTP_EXPIRED');

    expect($record->fresh()->used_at)->not->toBeNull();
});

it('requires authentication for the current user endpoint', function (): void {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('returns the authenticated user', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});
