<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.console.')
    ->group(function (): void {
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::view('/dashboard', 'admin.dashboard')->name('dashboard.alias');

        Route::prefix('operations')->name('operations.')->group(function (): void {
            Route::view('/users', 'admin.operations.users.index')->name('users.index');
            Route::view('/users/{userId}', 'admin.operations.users.show')->name('users.show');

            Route::view('/circles', 'admin.operations.circles.index')->name('circles.index');
            Route::view('/circles/{circleId}', 'admin.operations.circles.show')->name('circles.show');

            Route::view('/sos', 'admin.operations.sos.index')->name('sos.index');
            Route::view('/sos/{sosId}', 'admin.operations.sos.show')->name('sos.show');
        });
    });
