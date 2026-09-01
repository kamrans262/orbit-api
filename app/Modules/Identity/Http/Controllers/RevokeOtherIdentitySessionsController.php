<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\IdentitySession;
use App\Modules\Identity\Actions\RevokeIdentitySessionAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevokeOtherIdentitySessionsController
{
    public function __invoke(Request $request, RevokeIdentitySessionAction $action): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->getKey();
        $query = IdentitySession::query()
            ->where('user_id', $request->user()->getKey())
            ->where('status', 'active');

        if ($currentTokenId !== null) {
            $query->where(function ($builder) use ($currentTokenId): void {
                $builder->whereNull('access_token_id')->orWhere('access_token_id', '!=', $currentTokenId);
            });
        }

        $sessions = $query->get();

        foreach ($sessions as $session) {
            $action->handle($session, 'revoke_others', $request);
        }

        return response()->json(['data' => ['revoked' => $sessions->count()]]);
    }
}
