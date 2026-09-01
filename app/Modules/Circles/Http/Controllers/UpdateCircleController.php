<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Circles\Actions\UpdateCircleAction;
use App\Modules\Circles\Http\Requests\UpdateCircleRequest;
use App\Modules\Circles\Http\Resources\CircleResource;
use App\Modules\Circles\Services\CircleAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateCircleController extends Controller
{
    public function __invoke(
        UpdateCircleRequest $request,
        string $circleId,
        CircleAccess $access,
        UpdateCircleAction $action,
    ): JsonResponse {
        $circle = $access->findVisible($request->user(), $circleId);
        $access->assertActive($circle);
        $membership = $access->membership($request->user(), $circle);
        $circle = $action->handle($circle, $membership, $request->validated());
        $circle->loadCount('memberships');

        return ApiResponse::success(
            data: (new CircleResource($circle, $membership))->resolve($request),
            message: 'Circle updated successfully.',
        );
    }
}
