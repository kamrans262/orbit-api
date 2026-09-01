<?php

declare(strict_types=1);

use App\Modules\Media\Http\Controllers\CompleteMediaUploadController;
use App\Modules\Media\Http\Controllers\CreateMediaUploadController;
use App\Modules\Media\Http\Controllers\DeleteMediaAssetController;
use App\Modules\Media\Http\Controllers\DownloadEncryptedMediaController;
use App\Modules\Media\Http\Controllers\GetMediaAssetController;
use App\Modules\Media\Http\Controllers\GetMediaKeyEnvelopeController;
use App\Modules\Media\Http\Controllers\UploadMediaChunkController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->name('api.v1.')
    ->group(function (): void {
        Route::post('/circles/{circleId}/media/uploads', CreateMediaUploadController::class)
            ->middleware('throttle:30,1')
            ->name('circles.media.uploads.store');

        Route::put('/media/uploads/{uploadId}/chunks/{chunkIndex}', UploadMediaChunkController::class)
            ->middleware('throttle:240,1')
            ->name('media.uploads.chunks.store');

        Route::post('/media/uploads/{uploadId}/complete', CompleteMediaUploadController::class)
            ->middleware('throttle:30,1')
            ->name('media.uploads.complete');

        Route::get('/media/{assetId}', GetMediaAssetController::class)
            ->name('media.show');

        Route::get('/media/{assetId}/key-envelope', GetMediaKeyEnvelopeController::class)
            ->name('media.key-envelope');

        Route::get('/media/{assetId}/download', DownloadEncryptedMediaController::class)
            ->middleware('throttle:120,1')
            ->name('media.download');

        Route::delete('/media/{assetId}', DeleteMediaAssetController::class)
            ->name('media.destroy');
    });
