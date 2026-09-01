<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Actions\UpdateNotificationPreferencesAction;
use App\Modules\Notifications\Http\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;

final class UpdateNotificationPreferencesController
{
    public function __invoke(UpdateNotificationPreferencesRequest $request, UpdateNotificationPreferencesAction $update): JsonResponse
    {
        $p = $update->handle($request->user(), $request->validated());

        return response()->json(['data' => $p->only([
            'push_enabled', 'in_app_enabled', 'messages_enabled', 'moments_enabled', 'pings_enabled',
            'activity_enabled', 'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end', 'timezone',
        ])]);
    }
}
