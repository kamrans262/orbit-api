<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Actions\CompleteMediaUploadAction;
use App\Modules\Media\Http\Requests\CompleteMediaUploadRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CompleteMediaUploadController extends Controller
{
    public function __invoke(
        CompleteMediaUploadRequest $request,
        string $uploadId,
        CompleteMediaUploadAction $action,
    ): JsonResponse {
        $asset = $action->handle(
            $request->user(),
            $uploadId,
            $request->validated('key_envelopes'),
        );

        return ApiResponse::success(
            data: [
                'asset_id' => $asset->id,
                'circle_id' => $asset->circle_id,
                'kind' => $asset->kind->value,
                'size_bytes' => $asset->size_bytes,
                'sha256_ciphertext' => $asset->sha256_ciphertext,
                'status' => $asset->status->value,
                'expires_at' => $asset->expires_at?->toIso8601String(),
            ],
            message: 'Encrypted media upload completed.',
        );
    }
}
