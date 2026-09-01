<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Circles\Actions\LeaveCircleAction;
use App\Modules\Circles\Services\CircleAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeaveCircleController extends Controller
{
    public function __invoke(
        Request $request,
        string $circleId,
        CircleAccess $access,
        LeaveCircleAction $action,
    ): JsonResponse {
        $circle = $access->findVisible($request->user(), $circleId);
        $membership = $access->membership($request->user(), $circle);
        $action->handle($membership);

        return ApiResponse::success(
            data: null,
            message: 'You left the Circle successfully.',
        );
    }
}
