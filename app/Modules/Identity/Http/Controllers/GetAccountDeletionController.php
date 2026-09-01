<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\AccountDeletionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetAccountDeletionController
{
    public function __invoke(Request $request): JsonResponse
    {
        $deletion = AccountDeletionRequest::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('requested_at')
            ->first();

        return response()->json(['data' => $deletion ? [
            'id' => $deletion->id,
            'status' => $deletion->status,
            'requested_at' => $deletion->requested_at?->toIso8601String(),
            'scheduled_for' => $deletion->scheduled_for?->toIso8601String(),
            'blocking_reason' => $deletion->blocking_reason,
            'cancelled_at' => $deletion->cancelled_at?->toIso8601String(),
            'completed_at' => $deletion->completed_at?->toIso8601String(),
        ] : null]);
    }
}
