<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\IdentityRefreshToken;
use App\Models\IdentitySession;
use App\Modules\Identity\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RevokeIdentitySessionAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        IdentitySession $session,
        string $reason = 'user_revoked',
        ?Request $request = null,
        bool $writeAudit = true,
    ): void {
        if ($session->status === 'revoked') {
            return;
        }

        DB::transaction(function () use ($session, $reason): void {
            if ($session->access_token_id !== null) {
                DB::table('personal_access_tokens')->where('id', $session->access_token_id)->delete();
            }

            IdentityRefreshToken::query()
                ->where('family_id', $session->refresh_family_id)
                ->where('status', 'active')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            $session->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoke_reason' => $reason,
                'access_token_id' => null,
            ])->save();
        });

        if ($writeAudit) {
            $this->audit->write(
                'identity.session.revoked',
                (int) $session->user_id,
                targetType: 'identity_session',
                targetId: $session->id,
                metadata: ['device_id' => $session->device_id, 'reason' => $reason],
                request: $request,
            );
        }
    }
}
