<?php

declare(strict_types=1);

use App\Modules\Admin\BillingAdvertising\Http\Controllers\ListConsumerAdsController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\RecordConsumerAdEventController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ShowMySubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:240,1'])->group(function (): void {
    Route::get('/me/subscription', ShowMySubscriptionController::class)->name('api.v1.me.subscription');
    Route::get('/ads/{placement}', ListConsumerAdsController::class)->whereIn('placement', ['feed_card', 'map_pin'])->name('api.v1.ads.index');
    Route::post('/ads/{campaignId}/events', RecordConsumerAdEventController::class)->name('api.v1.ads.events.store');
});
