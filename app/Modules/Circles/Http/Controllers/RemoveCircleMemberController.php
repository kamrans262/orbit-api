<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Circles\Actions\RemoveCircleMemberAction;
use App\Modules\Circles\Services\CircleAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveCircleMemberController extends Controller
{
    public function __invoke(
        Request $request,
        string $circleId,
        string $membershipId,
        CircleAccess $access,
        RemoveCircleMemberAction $action,
    ): JsonResponse {
        $circle = $access->findVisible($request->user(), $circleId);
        $access->assertActive($circle);
        $requesterMembership = $access->membership($request->user(), $circle);
        $targetMembership = $access->member($circle, $membershipId);
        $action->handle($requesterMembership, $targetMembership);

        return ApiResponse::success(
            data: null,
            message: 'Circle member removed successfully.',
        );
    }
}
