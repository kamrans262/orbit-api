<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Circles\Actions\ArchiveCircleAction;
use App\Modules\Circles\Http\Resources\CircleResource;
use App\Modules\Circles\Services\CircleAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ArchiveCircleController extends Controller
{
    public function __invoke(
        Request $request,
        string $circleId,
        CircleAccess $access,
        ArchiveCircleAction $action,
    ): JsonResponse {
        $circle = $access->findVisible($request->user(), $circleId);
        $membership = $access->membership($request->user(), $circle);
        $circle = $action->handle($circle, $membership);
        $circle->loadCount('memberships');

        return ApiResponse::success(
            data: (new CircleResource($circle, $membership))->resolve($request),
            message: 'Circle archived successfully.',
        );
    }
}
