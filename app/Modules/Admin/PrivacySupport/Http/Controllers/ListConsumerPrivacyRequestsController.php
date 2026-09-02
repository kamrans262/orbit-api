<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListConsumerPrivacyRequestsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $rows = PrivacyRequest::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get()
            ->map(fn (PrivacyRequest $row): array => [
                'id' => $row->id,
                'type' => $row->type,
                'status' => $row->status,
                'identity_status' => $row->identity_status,
                'deadline_at' => $row->deadline_at?->toIso8601String(),
                'completed_at' => $row->completed_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }
}
