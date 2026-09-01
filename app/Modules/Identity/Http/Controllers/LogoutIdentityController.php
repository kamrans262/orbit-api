<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\IdentitySession;
use App\Modules\Identity\Actions\RevokeIdentitySessionAction;
use App\Modules\Identity\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutIdentityController
{
    public function __invoke(Request $request, RevokeIdentitySessionAction $revoke, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $tokenId = $token?->getKey();

        $session = $tokenId === null
            ? null
            : IdentitySession::query()
                ->where('user_id', $user->getKey())
                ->where('access_token_id', $tokenId)
                ->where('status', 'active')
                ->first();

        if ($session) {
            $revoke->handle($session, 'logout', $request, false);
        } elseif ($tokenId !== null) {
            $token?->delete();
        }

        $audit->write(
            'identity.sign_out',
            (int) $user->getKey(),
            targetType: $session ? 'identity_session' : 'access_token',
            targetId: $session?->id ?? ($tokenId !== null ? (string) $tokenId : null),
            request: $request,
        );

        return response()->json(['data' => ['signed_out' => true]]);
    }
}
