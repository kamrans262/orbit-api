<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminInvitation;
use App\Models\AdminMfaChallenge;
use App\Models\AdminSession;
use App\Models\AdminUser;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

final class PurgeExpiredAdminSecurityArtifactsCommand extends Command
{
    protected $signature = 'orbit:admin:purge-expired-security-artifacts';

    protected $description = 'Purge old administrator MFA challenges, stale invitations, and expired admin access tokens.';

    public function handle(): int
    {
        $challenges = AdminMfaChallenge::query()->where('expires_at', '<', now()->subDay())->delete();
        $invitations = AdminInvitation::query()->where('expires_at', '<', now()->subDays(30))->delete();

        $expiredSessions = AdminSession::query()
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now())
            ->get();

        $tokenIds = $expiredSessions->pluck('access_token_id')->filter()->all();
        if ($tokenIds !== []) {
            PersonalAccessToken::query()->whereIn('id', $tokenIds)->delete();
        }
        foreach ($expiredSessions as $session) {
            $session->forceFill(['revoked_at' => now(), 'revoke_reason' => 'expired_cleanup'])->save();
        }

        $orphanedTokens = PersonalAccessToken::query()
            ->where('tokenable_type', AdminUser::class)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->subDay())
            ->delete();

        $this->info(sprintf(
            'Purged %d MFA challenge(s), %d stale invitation(s), revoked %d expired admin session(s), and removed %d orphaned expired admin token(s).',
            $challenges,
            $invitations,
            $expiredSessions->count(),
            $orphanedTokens,
        ));

        return self::SUCCESS;
    }
}
