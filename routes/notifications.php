<?php

declare(strict_types=1);

use App\Modules\Notifications\Http\Controllers\GetNotificationPreferencesController;
use App\Modules\Notifications\Http\Controllers\ListNotificationsController;
use App\Modules\Notifications\Http\Controllers\MarkAllNotificationsReadController;
use App\Modules\Notifications\Http\Controllers\MarkNotificationReadController;
use App\Modules\Notifications\Http\Controllers\UpdateCircleNotificationPreferenceController;
use App\Modules\Notifications\Http\Controllers\UpdateNotificationPreferencesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:240,1'])->group(function (): void {
    Route::get('/notifications', ListNotificationsController::class)->name('api.v1.notifications.index');
    Route::get('/notifications/preferences', GetNotificationPreferencesController::class)->name('api.v1.notifications.preferences.show');
    Route::put('/notifications/preferences', UpdateNotificationPreferencesController::class)->name('api.v1.notifications.preferences.update');
    Route::put('/notifications/circles/{circleId}', UpdateCircleNotificationPreferenceController::class)->name('api.v1.notifications.circles.update');
    Route::post('/notifications/{notificationId}/read', MarkNotificationReadController::class)->name('api.v1.notifications.read');
    Route::post('/notifications/read-all', MarkAllNotificationsReadController::class)->name('api.v1.notifications.read-all');
});
