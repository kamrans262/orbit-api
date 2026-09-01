<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CircleMember;
use App\Modules\Circles\Http\Resources\CircleResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListCirclesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $memberships = CircleMember::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('circle', fn ($query) => $query->available())
            ->with(['circle' => fn ($query) => $query->withCount('memberships')])
            ->latest('joined_at')
            ->get();

        $data = $memberships
            ->map(fn (CircleMember $membership): array => (new CircleResource(
                $membership->circle,
                $membership,
            ))->resolve($request))
            ->values()
            ->all();

        return ApiResponse::success(data: $data);
    }
}
