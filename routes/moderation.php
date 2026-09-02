<?php

declare(strict_types=1);

use App\Modules\Admin\Moderation\Http\Controllers\CreateConsumerAppealController;
use App\Modules\Admin\Moderation\Http\Controllers\CreateConsumerReportController;
use App\Modules\Admin\Moderation\Http\Controllers\RequestAppealEmailOtpController;
use App\Modules\Admin\Moderation\Http\Controllers\VerifyAppealEmailOtpController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('appeals/auth/email-otp')->group(function (): void {
        Route::post('/request', RequestAppealEmailOtpController::class)
            ->middleware('throttle:email-otp-request')
            ->name('api.v1.appeals.auth.email-otp.request');

        Route::post('/verify', VerifyAppealEmailOtpController::class)
            ->middleware('throttle:email-otp-verify')
            ->name('api.v1.appeals.auth.email-otp.verify');
    });

    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::post('/reports', CreateConsumerReportController::class)
            ->middleware('throttle:20,1')
            ->name('api.v1.reports.store');

        Route::post('/appeals', CreateConsumerAppealController::class)
            ->middleware('throttle:10,1')
            ->name('api.v1.appeals.store');
    });
});
