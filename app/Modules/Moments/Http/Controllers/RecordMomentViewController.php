<?php

declare(strict_types=1);

namespace App\Modules\Moments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Moments\Actions\RecordMomentViewAction;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecordMomentViewController extends Controller
{
    public function __invoke(
        Request $request,
        string $momentId,
        RecordMomentViewAction $action,
    ): JsonResponse {
        $result = $action->handle($request->user(), $momentId);

        return ApiResponse::success(
            data: $result,
            message: 'Moment view processed.',
        );
    }
}
