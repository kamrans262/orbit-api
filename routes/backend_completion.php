<?php

declare(strict_types=1);

use App\Modules\Dashboard\Http\Controllers\ShowDashboardSummaryController;
use App\Modules\Dashboard\Http\Controllers\ShowMemberRecentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/dashboard/summary', ShowDashboardSummaryController::class)->name('api.v1.dashboard.summary');
    Route::get('/users/{userId}/recent', ShowMemberRecentController::class)->whereNumber('userId')->name('api.v1.users.recent');
});
