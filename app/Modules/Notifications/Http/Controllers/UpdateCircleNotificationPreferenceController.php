<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Actions\UpdateCircleNotificationPreferenceAction;
use App\Modules\Notifications\Http\Requests\UpdateCircleNotificationPreferenceRequest;
use Illuminate\Http\JsonResponse;

final class UpdateCircleNotificationPreferenceController
{
    public function __invoke(UpdateCircleNotificationPreferenceRequest $request, string $circleId, UpdateCircleNotificationPreferenceAction $update): JsonResponse
    {
        $preference = $update->handle($request->user(), $circleId, $request->validated());

        return response()->json(['data' => [
            'circle_id' => $preference->circle_id,
            'muted_until' => $preference->muted_until?->toIso8601String(),
            'silent' => (bool) $preference->silent,
        ]]);
    }
}
