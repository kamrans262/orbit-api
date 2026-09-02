<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateConsumerPrivacyRequestController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'type' => ['required', Rule::in(['access', 'correction', 'consent'])],
            'details' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        $privacy = PrivacyRequest::query()->create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'source' => 'consumer',
            'status' => 'new',
            'identity_status' => 'account_authenticated',
            'details' => $data['details'],
            'deadline_at' => now()->addDays(30),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $privacy->id,
                'type' => $privacy->type,
                'status' => $privacy->status,
                'deadline_at' => $privacy->deadline_at?->toIso8601String(),
            ],
        ], 202);
    }
}
