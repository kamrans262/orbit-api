<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RequestAccountDeletionAction;
use App\Modules\Identity\Http\Requests\RequestAccountDeletionRequest;
use Illuminate\Http\JsonResponse;

final class RequestAccountDeletionController
{
    public function __invoke(
        RequestAccountDeletionRequest $request,
        RequestAccountDeletionAction $action,
    ): JsonResponse {
        $deletion = $action->handle(
            $request->user(),
            $request->validated('reason'),
            $request,
        );

        return response()->json(['data' => [
            'id' => $deletion->id,
            'status' => $deletion->status,
            'requested_at' => $deletion->requested_at?->toIso8601String(),
            'scheduled_for' => $deletion->scheduled_for?->toIso8601String(),
        ]], 202);
    }
}
