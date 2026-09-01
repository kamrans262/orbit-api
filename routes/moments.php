<?php

declare(strict_types=1);

use App\Modules\Moments\Http\Controllers\DeleteMomentController;
use App\Modules\Moments\Http\Controllers\ListCircleMomentsController;
use App\Modules\Moments\Http\Controllers\ListMomentViewersController;
use App\Modules\Moments\Http\Controllers\PublishMomentController;
use App\Modules\Moments\Http\Controllers\RecordMomentViewController;
use App\Modules\Moments\Http\Controllers\ShowMomentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('/circles/{circleId}/moments', ListCircleMomentsController::class)
            ->name('circles.moments.index');

        Route::post('/circles/{circleId}/moments', PublishMomentController::class)
            ->middleware('throttle:30,1')
            ->name('circles.moments.store');

        Route::get('/moments/{momentId}', ShowMomentController::class)
            ->name('moments.show');

        Route::post('/moments/{momentId}/view', RecordMomentViewController::class)
            ->middleware('throttle:120,1')
            ->name('moments.view');

        Route::get('/moments/{momentId}/viewers', ListMomentViewersController::class)
            ->name('moments.viewers');

        Route::delete('/moments/{momentId}', DeleteMomentController::class)
            ->name('moments.destroy');
    });
