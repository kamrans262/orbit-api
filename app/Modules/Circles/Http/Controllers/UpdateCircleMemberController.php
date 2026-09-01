<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Circles\Actions\UpdateCircleMemberAction;
use App\Modules\Circles\Http\Requests\UpdateCircleMemberRequest;
use App\Modules\Circles\Http\Resources\CircleMemberResource;
use App\Modules\Circles\Services\CircleAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateCircleMemberController extends Controller
{
    public function __invoke(
        UpdateCircleMemberRequest $request,
        string $circleId,
        string $membershipId,
        CircleAccess $access,
        UpdateCircleMemberAction $action,
    ): JsonResponse {
        $circle = $access->findVisible($request->user(), $circleId);
        $access->assertActive($circle);
        $requesterMembership = $access->membership($request->user(), $circle);
        $targetMembership = $access->member($circle, $membershipId);
        $targetMembership = $action->handle($requesterMembership, $targetMembership, $request->validated());
        $targetMembership->load('user');

        return ApiResponse::success(
            data: CircleMemberResource::make($targetMembership)->resolve($request),
            message: 'Circle member updated successfully.',
        );
    }
}
