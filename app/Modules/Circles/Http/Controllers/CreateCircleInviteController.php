<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Circles\Actions\CreateCircleInviteAction;
use App\Modules\Circles\Http\Requests\CreateCircleInviteRequest;
use App\Modules\Circles\Http\Resources\CircleInviteResource;
use App\Modules\Circles\Services\CircleAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateCircleInviteController extends Controller
{
    public function __invoke(
        CreateCircleInviteRequest $request,
        string $circleId,
        CircleAccess $access,
        CreateCircleInviteAction $action,
    ): JsonResponse {
        $circle = $access->findVisible($request->user(), $circleId);
        $membership = $access->membership($request->user(), $circle);
        $result = $action->handle($request->user(), $circle, $membership, $request->validated());

        return ApiResponse::success(
            data: (new CircleInviteResource($result['invite'], $result['code']))->resolve($request),
            message: 'Circle invite created successfully.',
            status: 201,
        );
    }
}
