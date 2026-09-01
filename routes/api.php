<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Controllers\LogoutController;
use App\Modules\Auth\Http\Controllers\MeController;
use App\Modules\Auth\Http\Controllers\RequestEmailOtpController;
use App\Modules\Auth\Http\Controllers\VerifyEmailOtpController;
use App\Modules\Devices\Http\Controllers\ListDevicesController;
use App\Modules\Devices\Http\Controllers\RegisterDeviceController;
use App\Modules\Devices\Http\Controllers\RevokeDeviceController;
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
        });
    });
