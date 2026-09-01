<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\IdentityRefreshToken;
use App\Models\IdentitySession;
use App\Models\User;

final class IdentityTokenService
{
    public const ACCESS_MINUTES = 15;

    public const REFRESH_DAYS = 60;

    public function createPair(User $user, IdentitySession $session): array
    {
        $accessExpiresAt = now()->addMinutes(self::ACCESS_MINUTES);
        $refreshExpiresAt = now()->addDays(self::REFRESH_DAYS);

        $access = $user->createToken(
            'orbit-access:'.$session->id,
            ['*'],
            $accessExpiresAt,
        );

        $rawRefresh = $this->newRefreshSecret();

        IdentityRefreshToken::query()->create([
            'session_id' => $session->id,
            'user_id' => $user->getKey(),
            'device_id' => $session->device_id,
            'family_id' => $session->refresh_family_id,
            'token_hash' => $this->hash($rawRefresh),
            'status' => 'active',
            'expires_at' => $refreshExpiresAt,
        ]);

        $session->forceFill([
            'access_token_id' => $access->accessToken->getKey(),
            'last_seen_at' => now(),
            'access_expires_at' => $accessExpiresAt,
            'refresh_expires_at' => $refreshExpiresAt,
        ])->save();

        return [
            'token_type' => 'Bearer',
            'access_token' => $access->plainTextToken,
            'access_expires_at' => $accessExpiresAt->toIso8601String(),
            'refresh_token' => $rawRefresh,
            'refresh_expires_at' => $refreshExpiresAt->toIso8601String(),
            'session_id' => $session->id,
        ];
    }

    public function newRefreshSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function fingerprint(?string $publicKey): ?string
    {
        if ($publicKey === null || $publicKey === '') {
            return null;
        }

        return hash('sha256', $publicKey);
    }
}
