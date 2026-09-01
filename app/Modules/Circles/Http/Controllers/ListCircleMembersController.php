<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CircleMember;
use App\Modules\Circles\Http\Resources\CircleMemberResource;
use App\Modules\Circles\Services\CircleAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListCircleMembersController extends Controller
{
    public function __invoke(Request $request, string $circleId, CircleAccess $access): JsonResponse
    {
        $circle = $access->findVisible($request->user(), $circleId);

        $members = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->with('user')
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'member' THEN 3 ELSE 4 END")
            ->orderBy('joined_at')
            ->get();

        return ApiResponse::success(
            data: CircleMemberResource::collection($members)->resolve($request),
        );
    }
}
