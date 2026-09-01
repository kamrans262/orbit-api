<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Actions\DeleteMediaAssetAction;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteMediaAssetController extends Controller
{
    public function __invoke(
        Request $request,
        string $assetId,
        DeleteMediaAssetAction $action,
    ): JsonResponse {
        $action->handle($request->user(), $assetId);

        return ApiResponse::success(
            data: null,
            message: 'Encrypted media deleted.',
        );
    }
}
