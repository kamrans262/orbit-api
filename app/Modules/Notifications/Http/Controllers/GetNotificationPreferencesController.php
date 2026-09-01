<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Services\NotificationPreferencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetNotificationPreferencesController
{
    public function __invoke(Request $request, NotificationPreferencesService $preferences): JsonResponse
    {
        $p = $preferences->forUser((int) $request->user()->getKey());

        return response()->json(['data' => [
            'push_enabled' => (bool) $p->push_enabled,
            'in_app_enabled' => (bool) $p->in_app_enabled,
            'messages_enabled' => (bool) $p->messages_enabled,
            'moments_enabled' => (bool) $p->moments_enabled,
            'pings_enabled' => (bool) $p->pings_enabled,
            'activity_enabled' => (bool) $p->activity_enabled,
            'quiet_hours_enabled' => (bool) $p->quiet_hours_enabled,
            'quiet_hours_start' => $p->quiet_hours_start,
            'quiet_hours_end' => $p->quiet_hours_end,
            'timezone' => $p->timezone,
        ]]);
    }
}
