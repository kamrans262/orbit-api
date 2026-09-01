<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Modules\Media\Enums\MediaAssetStatus;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Services\CircleMediaAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetMediaAssetController extends Controller
{
    public function __invoke(
        Request $request,
        string $assetId,
        CircleMediaAccess $access,
    ): JsonResponse {
        $asset = MediaAsset::query()->whereKey($assetId)->first();

        if ($asset === null || $asset->status !== MediaAssetStatus::Ready) {
            throw MediaException::assetNotFound();
        }

        $access->membership($request->user(), $asset->circle_id);

        return ApiResponse::success(
            data: [
                'asset_id' => $asset->id,
                'circle_id' => $asset->circle_id,
                'kind' => $asset->kind->value,
                'content_type_hint' => $asset->content_type_hint,
                'size_bytes' => $asset->size_bytes,
                'sha256_ciphertext' => $asset->sha256_ciphertext,
                'expires_at' => $asset->expires_at?->toIso8601String(),
                'created_at' => $asset->created_at?->toIso8601String(),
            ],
            message: 'Encrypted media metadata retrieved.',
        );
    }
}
