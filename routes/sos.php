<?php

declare(strict_types=1);

use App\Modules\Sos\Http\Controllers\ActivateSosController;
use App\Modules\Sos\Http\Controllers\AttachSosRecordingController;
use App\Modules\Sos\Http\Controllers\ResolveSosController;
use App\Modules\Sos\Http\Controllers\RespondToSosController;
use App\Modules\Sos\Http\Controllers\ShowSosController;
use App\Modules\Sos\Http\Controllers\UpdateSosLocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:240,1'])->group(function (): void {
    Route::post('/sos/activate', ActivateSosController::class)->name('api.v1.sos.activate');
    Route::get('/sos/{sosId}', ShowSosController::class)->name('api.v1.sos.show');
    Route::post('/sos/{sosId}/respond', RespondToSosController::class)->name('api.v1.sos.respond');
    Route::put('/sos/{sosId}/location', UpdateSosLocationController::class)->name('api.v1.sos.location.update');
    Route::put('/sos/{sosId}/recording', AttachSosRecordingController::class)->name('api.v1.sos.recording.attach');
    Route::post('/sos/{sosId}/resolve', ResolveSosController::class)->name('api.v1.sos.resolve');
});
