<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Http\Requests\ListNotificationsRequest;
use App\Modules\Notifications\Services\NotificationFeedService;
use Illuminate\Http\JsonResponse;

final class ListNotificationsController
{
    public function __invoke(ListNotificationsRequest $request, NotificationFeedService $feed): JsonResponse
    {
        return response()->json($feed->list($request->user(), (int) ($request->validated()['limit'] ?? 20)));
    }
}
