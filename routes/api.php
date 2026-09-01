<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Controllers\LogoutController;
use App\Modules\Auth\Http\Controllers\MeController;
use App\Modules\Auth\Http\Controllers\RequestEmailOtpController;
use App\Modules\Auth\Http\Controllers\VerifyEmailOtpController;
use App\Modules\Circles\Http\Controllers\ArchiveCircleController;
use App\Modules\Circles\Http\Controllers\CreateCircleController;
use App\Modules\Circles\Http\Controllers\CreateCircleInviteController;
use App\Modules\Circles\Http\Controllers\JoinCircleController;
use App\Modules\Circles\Http\Controllers\LeaveCircleController;
use App\Modules\Circles\Http\Controllers\ListCircleMembersController;
use App\Modules\Circles\Http\Controllers\ListCirclesController;
use App\Modules\Circles\Http\Controllers\RemoveCircleMemberController;
use App\Modules\Circles\Http\Controllers\ShowCircleController;
use App\Modules\Circles\Http\Controllers\UpdateCircleController;
use App\Modules\Circles\Http\Controllers\UpdateCircleMemberController;
use App\Modules\Devices\Http\Controllers\ListDevicesController;
use App\Modules\Devices\Http\Controllers\RegisterDeviceController;
use App\Modules\Devices\Http\Controllers\RevokeDeviceController;
use App\Modules\Presence\Http\Controllers\GetCircleMemberPresenceController;
use App\Modules\Presence\Http\Controllers\GetMyPresenceController;
use App\Modules\Presence\Http\Controllers\ListCirclePresenceController;
use App\Modules\Presence\Http\Controllers\UpdatePresenceController;
use App\Modules\Presence\Http\Controllers\UpdatePresenceSettingsController;
use App\Modules\Profile\Http\Controllers\GetProfileController;
use App\Modules\Profile\Http\Controllers\UpdateProfileController;
use App\Modules\System\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('/health', HealthController::class)->name('health');

        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('/email-otp/request', RequestEmailOtpController::class)
                ->middleware('throttle:email-otp-request')
                ->name('email-otp.request');

            Route::post('/email-otp/verify', VerifyEmailOtpController::class)
                ->middleware('throttle:email-otp-verify')
                ->name('email-otp.verify');

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::get('/me', MeController::class)->name('me');
                Route::post('/logout', LogoutController::class)->name('logout');
            });
        });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/profile', GetProfileController::class)->name('profile.show');
            Route::patch('/profile', UpdateProfileController::class)->name('profile.update');

            Route::get('/devices', ListDevicesController::class)->name('devices.index');
            Route::post('/devices', RegisterDeviceController::class)->name('devices.store');
            Route::delete('/devices/{deviceId}', RevokeDeviceController::class)->name('devices.revoke');

            Route::prefix('presence')->name('presence.')->group(function (): void {
                Route::put('/', UpdatePresenceController::class)
                    ->middleware('throttle:120,1')
                    ->name('update');
                Route::get('/me', GetMyPresenceController::class)->name('me');
                Route::patch('/settings', UpdatePresenceSettingsController::class)->name('settings.update');
            });

            Route::prefix('circles')->name('circles.')->group(function (): void {
                Route::get('/', ListCirclesController::class)->name('index');
                Route::post('/', CreateCircleController::class)->name('store');
                Route::post('/join', JoinCircleController::class)->name('join');

                Route::get('/{circleId}/presence', ListCirclePresenceController::class)
                    ->name('presence.index');
                Route::get('/{circleId}/members/{membershipId}/presence', GetCircleMemberPresenceController::class)
                    ->name('members.presence.show');

                Route::get('/{circleId}', ShowCircleController::class)->name('show');
                Route::patch('/{circleId}', UpdateCircleController::class)->name('update');
                Route::delete('/{circleId}', ArchiveCircleController::class)->name('archive');
                Route::post('/{circleId}/invites', CreateCircleInviteController::class)->name('invites.store');
                Route::get('/{circleId}/members', ListCircleMembersController::class)->name('members.index');
                Route::patch('/{circleId}/members/{membershipId}', UpdateCircleMemberController::class)
                    ->name('members.update');
                Route::delete('/{circleId}/members/{membershipId}', RemoveCircleMemberController::class)
                    ->name('members.destroy');
                Route::post('/{circleId}/leave', LeaveCircleController::class)->name('leave');
            });
        });
    });
