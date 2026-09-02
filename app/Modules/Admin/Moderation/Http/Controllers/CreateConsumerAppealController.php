<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Admin\Moderation\Services\ModerationAppealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateConsumerAppealController
{
    public function __invoke(Request $request, ModerationAppealService $appeals): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['success' => false, 'code' => 'UNAUTHENTICATED'], 401);
        }

        if (! $user->tokenCan('appeals:submit')) {
            return response()->json([
                'success' => false,
                'message' => 'This credential cannot submit account appeals.',
                'code' => 'TOKEN_SCOPE_RESTRICTED',
            ], 403);
        }

        $data = $request->validate([
            'enforcement_id' => ['required', 'uuid'],
            'explanation' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        try {
            $appeal = $appeals->submit($user, $data['enforcement_id'], $data['explanation']);
        } catch (ModerationDomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->status);
        }

        return response()->json([
            'success' => true,
            'data' => ['id' => (string) $appeal->id, 'status' => $appeal->status],
        ], 202);
    }
}
