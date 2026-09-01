<?php

declare(strict_types=1);

namespace App\Modules\Moments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Moments\Actions\DeleteMomentAction;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteMomentController extends Controller
{
    public function __invoke(
        Request $request,
        string $momentId,
        DeleteMomentAction $action,
    ): JsonResponse {
        $action->handle($request->user(), $momentId);

        return ApiResponse::success(
            data: null,
            message: 'Moment deleted.',
        );
    }
}
