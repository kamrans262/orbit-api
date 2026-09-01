<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Actions\MarkNotificationReadAction;
use App\Modules\Notifications\Services\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MarkNotificationReadController
{
    public function __invoke(Request $request, string $notificationId, MarkNotificationReadAction $mark, NotificationPresenter $presenter): JsonResponse
    {
        return response()->json(['data' => $presenter->present($mark->handle($request->user(), $notificationId))]);
    }
}
