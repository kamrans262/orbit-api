<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Actions\MarkAllNotificationsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MarkAllNotificationsReadController
{
    public function __invoke(Request $request, MarkAllNotificationsReadAction $mark): JsonResponse
    {
        return response()->json(['data' => ['updated' => $mark->handle($request->user())]]);
    }
}
