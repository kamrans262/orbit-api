<?php

declare(strict_types=1);
use App\Modules\Admin\AnalyticsOperations\Http\Controllers\RuntimePlatformController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:240,1'])->group(function (): void {
    Route::get('/platform/runtime', RuntimePlatformController::class);
});
