<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowConsumerPrivacyRequestController
{
    public function __invoke(Request $request, string $privacyRequestId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $row = PrivacyRequest::query()
            ->whereKey($privacyRequestId)
            ->where('user_id', $user->id)
            ->first();

        if ($row === null) {
            return response()->json(['success' => false, 'code' => 'PRIVACY_REQUEST_NOT_FOUND'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $row->id,
                'type' => $row->type,
                'status' => $row->status,
                'identity_status' => $row->identity_status,
                'details' => $row->details,
                'resolution' => $row->resolution,
                'deadline_at' => $row->deadline_at?->toIso8601String(),
                'completed_at' => $row->completed_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ],
        ]);
    }
}
