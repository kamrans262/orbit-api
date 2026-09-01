<?php

declare(strict_types=1);

use App\Modules\Activity\Http\Controllers\HideActivityController;
use App\Modules\Activity\Http\Controllers\ListActivityFeedController;
use App\Modules\Activity\Http\Controllers\ReportActivityController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:240,1'])->group(function (): void {
    Route::get('/activity/feed', ListActivityFeedController::class)->name('api.v1.activity.feed');
    Route::post('/activity/{activityId}/hide', HideActivityController::class)->name('api.v1.activity.hide');
    Route::post('/activity/{activityId}/report', ReportActivityController::class)->name('api.v1.activity.report');
});
