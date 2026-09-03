<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

$sourceRoute = Route::getRoutes()->getByName('admin.console.operations.moderation.index');
$middleware = $sourceRoute?->gatherMiddleware() ?? ['web'];

Route::middleware($middleware)
    ->prefix('admin/operations/support')
    ->name('admin.console.operations.support.')
    ->group(function (): void {
        Route::view('/', 'admin.console.operations.support.index')->name('index');
        Route::get('/{ticketId}', function (string $ticketId) {
            return view('admin.console.operations.support.show', ['ticketId' => $ticketId]);
        })->where('ticketId', '[A-Za-z0-9._:-]+')->name('show');
    });
