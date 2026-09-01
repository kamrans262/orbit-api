<?php

declare(strict_types=1);

namespace App\Modules\Presence\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CircleMember;
use App\Modules\Circles\Services\CircleAccess;
use App\Modules\Presence\Services\PresencePresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListCirclePresenceController extends Controller
{
    public function __invoke(
        Request $request,
        string $circleId,
        CircleAccess $access,
        PresencePresenter $presenter,
    ): JsonResponse {
        $circle = $access->findVisible($request->user(), $circleId);

        $members = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->with(['user.presenceState'])
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'member' THEN 3 ELSE 4 END")
            ->orderBy('joined_at')
            ->get();

        $data = $members->map(function (CircleMember $membership) use ($presenter): array {
            return [
                'membership_id' => $membership->id,
                'user' => [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                ],
                'role' => $membership->role->value,
                'configured_location_mode' => $membership->location_mode->value,
                'presence' => $presenter->forCircle($membership, $membership->user->presenceState),
            ];
        })->values()->all();

        return ApiResponse::success(data: $data);
    }
}
