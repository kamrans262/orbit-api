<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RefreshIdentitySessionAction;
use App\Modules\Identity\Http\Requests\RefreshIdentitySessionRequest;
use Illuminate\Http\JsonResponse;

final class RefreshIdentitySessionController
{
    public function __invoke(RefreshIdentitySessionRequest $request, RefreshIdentitySessionAction $action): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'data' => $action->handle(
                (string) $validated['refresh_token'],
                (string) $validated['device_id'],
                $request,
            ),
        ]);
    }
}
