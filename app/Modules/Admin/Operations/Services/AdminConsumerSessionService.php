<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Services;

use App\Models\IdentityRefreshToken;
use App\Models\IdentitySession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class AdminConsumerSessionService
{
    /** @return array{access_tokens:int,identity_sessions:int,refresh_tokens:int} */
    public function revokeAll(User $user, string $reason): array
    {
        return DB::transaction(function () use ($user, $reason): array {
            $sessions = IdentitySession::query()
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->get();

            $accessTokenIds = $sessions->pluck('access_token_id')->filter()->map(fn ($id): int => (int) $id)->all();

            $identityCount = IdentitySession::query()
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoke_reason' => mb_substr($reason, 0, 80),
                    'updated_at' => now(),
                ]);

            $refreshCount = IdentityRefreshToken::query()
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            $tokenQuery = PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->getKey());
            $accessCount = $tokenQuery->count();
            $tokenQuery->delete();

            if ($accessTokenIds !== []) {
                PersonalAccessToken::query()->whereIn('id', $accessTokenIds)->delete();
            }

            return [
                'access_tokens' => $accessCount,
                'identity_sessions' => $identityCount,
                'refresh_tokens' => $refreshCount,
            ];
        });
    }

    public function revokeSession(User $user, string $sessionId, string $reason): bool
    {
        return DB::transaction(function () use ($user, $sessionId, $reason): bool {
            $session = IdentitySession::query()
                ->whereKey($sessionId)
                ->where('user_id', $user->getKey())
                ->first();

            if ($session === null) {
                return false;
            }

            if ($session->access_token_id !== null) {
                PersonalAccessToken::query()->whereKey($session->access_token_id)->delete();
            }

            IdentityRefreshToken::query()
                ->where('session_id', $session->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked', 'revoked_at' => now(), 'updated_at' => now()]);

            if ($session->status === 'active') {
                $session->forceFill([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoke_reason' => mb_substr($reason, 0, 80),
                ])->save();
            }

            return true;
        });
    }

    /** @return array{rotated:int} */
    public function forceAccessRotation(User $user, string $deviceId): array
    {
        return DB::transaction(function () use ($user, $deviceId): array {
            $sessions = IdentitySession::query()
                ->where('user_id', $user->getKey())
                ->where('device_id', $deviceId)
                ->where('status', 'active')
                ->get();

            $rotated = 0;
            foreach ($sessions as $session) {
                if ($session->access_token_id !== null) {
                    PersonalAccessToken::query()->whereKey($session->access_token_id)->delete();
                }

                $session->forceFill([
                    'access_token_id' => null,
                    'access_expires_at' => now(),
                ])->save();
                $rotated++;
            }

            return ['rotated' => $rotated];
        });
    }

    /** @return array{revoked_sessions:int} */
    public function revokeDeviceSessions(User $user, string $deviceId, string $reason): array
    {
        $sessions = IdentitySession::query()
            ->where('user_id', $user->getKey())
            ->where('device_id', $deviceId)
            ->pluck('id')
            ->all();

        $count = 0;
        foreach ($sessions as $sessionId) {
            $count += $this->revokeSession($user, (string) $sessionId, $reason) ? 1 : 0;
        }

        return ['revoked_sessions' => $count];
    }
}
