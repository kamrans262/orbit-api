<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Actions\StoreMediaChunkAction;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UploadMediaChunkController extends Controller
{
    public function __invoke(
        Request $request,
        string $uploadId,
        string $chunkIndex,
        StoreMediaChunkAction $action,
    ): JsonResponse {
        abort_unless(ctype_digit($chunkIndex), 404);

        $action->handle(
            $request->user(),
            $uploadId,
            (int) $chunkIndex,
            $request->getContent(),
            $request->header('X-Chunk-SHA256'),
        );

        return ApiResponse::success(
            data: ['chunk_index' => (int) $chunkIndex],
            message: 'Encrypted media chunk stored.',
        );
    }
}
