<?php

declare(strict_types=1);

namespace App\Modules\Presence\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Circles\Services\CircleAccess;
use App\Modules\Presence\Services\PresencePresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetCircleMemberPresenceController extends Controller
{
    public function __invoke(
        Request $request,
        string $circleId,
        string $membershipId,
        CircleAccess $access,
        PresencePresenter $presenter,
    ): JsonResponse {
        $circle = $access->findVisible($request->user(), $circleId);
        $membership = $access->member($circle, $membershipId);
        $membership->load('user.presenceState');

        return ApiResponse::success(data: [
            'membership_id' => $membership->id,
            'user' => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
            ],
            'role' => $membership->role->value,
            'configured_location_mode' => $membership->location_mode->value,
            'presence' => $presenter->forCircle($membership, $membership->user->presenceState),
        ]);
    }
}
