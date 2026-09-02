<?php

declare(strict_types=1);

use App\Modules\Admin\CommunicationsContent\Http\Controllers\AcceptLegalDocumentController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListConsumerAnnouncementsController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListConsumerLegalDocumentsController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ShowConsumerContentController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ShowPlatformConfigController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\UpdateConsumerRegionalProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/platform/config', ShowPlatformConfigController::class)->middleware('throttle:120,1');

    Route::middleware(['auth:sanctum', 'throttle:240,1'])->group(function (): void {
        Route::put('/platform/profile', UpdateConsumerRegionalProfileController::class);
        Route::get('/communications/announcements', ListConsumerAnnouncementsController::class);
        Route::get('/content/{slug}', ShowConsumerContentController::class);
        Route::get('/legal/documents', ListConsumerLegalDocumentsController::class);
        Route::post('/legal/documents/{legalId}/accept', AcceptLegalDocumentController::class);
    });
});
