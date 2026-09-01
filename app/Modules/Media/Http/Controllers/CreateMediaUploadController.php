<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Actions\CreateMediaUploadAction;
use App\Modules\Media\Http\Requests\CreateMediaUploadRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateMediaUploadController extends Controller
{
    public function __invoke(
        CreateMediaUploadRequest $request,
        string $circleId,
        CreateMediaUploadAction $action,
    ): JsonResponse {
        $upload = $action->handle($request->user(), $circleId, $request->validated());

        return ApiResponse::success(
            data: [
                'upload_id' => $upload->id,
                'asset_id' => $upload->asset_id,
                'chunk_size_bytes' => $upload->chunk_size_bytes,
                'total_chunks' => $upload->total_chunks,
                'expires_at' => $upload->expires_at->toIso8601String(),
            ],
            message: 'Encrypted media upload created.',
            status: 201,
        );
    }
}
