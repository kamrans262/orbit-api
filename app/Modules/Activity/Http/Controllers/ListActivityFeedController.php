<?php

declare(strict_types=1);

namespace App\Modules\Activity\Http\Controllers;

use App\Modules\Activity\Http\Requests\ListActivityFeedRequest;
use App\Modules\Activity\Services\ActivityFeedService;
use Illuminate\Http\JsonResponse;

final class ListActivityFeedController
{
    public function __invoke(ListActivityFeedRequest $request, ActivityFeedService $feed): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $feed->feed($request->user(), (int) ($validated['limit'] ?? 20)),
        );
    }
}
