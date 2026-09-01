<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\IdentitySession;
use App\Modules\Identity\Actions\RevokeIdentitySessionAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevokeIdentitySessionController
{
    public function __invoke(Request $request, string $sessionId, RevokeIdentitySessionAction $action): JsonResponse
    {
        $session = IdentitySession::query()
            ->whereKey($sessionId)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if (! $session) {
            return response()->json(['error' => ['code' => 'identity_session_not_found']], 404);
        }

        $action->handle($session, 'user_revoked', $request);

        return response()->json(['data' => ['id' => $session->id, 'status' => 'revoked']]);
    }
}
