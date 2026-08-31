<?php

declare(strict_types=1);

use App\Modules\System\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('/health', HealthController::class)
            ->name('health');
    });
