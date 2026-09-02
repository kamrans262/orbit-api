<?php

declare(strict_types=1);

use App\Modules\Admin\PrivacySupport\Http\Controllers\CreateConsumerPrivacyRequestController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\CreateConsumerSupportTicketController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ListConsumerPrivacyRequestsController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ListConsumerSupportTicketsController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\RedeemPrivacyExportDeliveryController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ReplyConsumerSupportTicketController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ShowConsumerPrivacyRequestController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ShowConsumerSupportTicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:240,1'])->group(function (): void {
    Route::get('/privacy/requests', ListConsumerPrivacyRequestsController::class)->name('api.v1.privacy.requests.index');
    Route::post('/privacy/requests', CreateConsumerPrivacyRequestController::class)->name('api.v1.privacy.requests.store');
    Route::get('/privacy/requests/{privacyRequestId}', ShowConsumerPrivacyRequestController::class)->name('api.v1.privacy.requests.show');
    Route::get('/privacy/export-deliveries/{token}', RedeemPrivacyExportDeliveryController::class)->name('api.v1.privacy.export-deliveries.show');

    Route::get('/support/tickets', ListConsumerSupportTicketsController::class)->name('api.v1.support.tickets.index');
    Route::post('/support/tickets', CreateConsumerSupportTicketController::class)->name('api.v1.support.tickets.store');
    Route::get('/support/tickets/{ticketId}', ShowConsumerSupportTicketController::class)->name('api.v1.support.tickets.show');
    Route::post('/support/tickets/{ticketId}/messages', ReplyConsumerSupportTicketController::class)->name('api.v1.support.tickets.messages.store');
});
