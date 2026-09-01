<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\IdentityRefreshToken;
use App\Models\IdentitySession;
use App\Models\User;
use App\Modules\Identity\Services\AuditLogger;
use App\Modules\Identity\Services\IdentityTokenService;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class RefreshIdentitySessionAction
{
    public function __construct(
        private readonly IdentityTokenService $tokens,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(string $rawRefreshToken, string $deviceId, ?Request $request = null): array
    {
        $hash = $this->tokens->hash($rawRefreshToken);

        $result = DB::transaction(function () use ($hash, $deviceId): array {
            /** @var IdentityRefreshToken|null $refresh */
            $refresh = IdentityRefreshToken::query()
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (! $refresh) {
                return ['error' => 'invalid'];
            }

            /** @var IdentitySession|null $session */
            $session = IdentitySession::query()->whereKey($refresh->session_id)->lockForUpdate()->first();

            if (! $session || $session->status !== 'active') {
                return ['error' => 'inactive'];
            }

            if ($refresh->device_id !== $deviceId || $session->device_id !== $deviceId) {
                $this->revokeFamily($refresh, $session, 'device_mismatch');

                return [
                    'error' => 'device_mismatch',
                    'user_id' => (int) $refresh->user_id,
                    'session_id' => $session->id,
                ];
            }

            if ($refresh->status !== 'active') {
                $this->revokeFamily($refresh, $session, 'refresh_reuse_detected', true);

                return [
                    'error' => 'reuse',
                    'user_id' => (int) $refresh->user_id,
                    'session_id' => $session->id,
                    'family_id' => $refresh->family_id,
                ];
            }

            if ($refresh->expires_at?->isPast()) {
                $this->revokeFamily($refresh, $session, 'refresh_expired');

                return [
                    'error' => 'expired',
                    'user_id' => (int) $refresh->user_id,
                    'session_id' => $session->id,
                ];
            }

            $user = User::query()->findOrFail($refresh->user_id);

            if ($session->access_token_id !== null) {
                DB::table('personal_access_tokens')->where('id', $session->access_token_id)->delete();
            }

            $rawNext = $this->tokens->newRefreshSecret();
            $next = IdentityRefreshToken::query()->create([
                'session_id' => $session->id,
                'user_id' => $refresh->user_id,
                'device_id' => $deviceId,
                'family_id' => $refresh->family_id,
                'token_hash' => $this->tokens->hash($rawNext),
                'status' => 'active',
                'expires_at' => now()->addDays(IdentityTokenService::REFRESH_DAYS),
            ]);

            $refresh->forceFill([
                'status' => 'rotated',
                'replaced_by_id' => $next->id,
                'rotated_at' => now(),
            ])->save();

            $accessExpiresAt = now()->addMinutes(IdentityTokenService::ACCESS_MINUTES);
            $access = $user->createToken('orbit-access:'.$session->id, ['*'], $accessExpiresAt);

            $session->forceFill([
                'access_token_id' => $access->accessToken->getKey(),
                'last_seen_at' => now(),
                'access_expires_at' => $accessExpiresAt,
                'refresh_expires_at' => $next->expires_at,
            ])->save();

            return [
                'token_type' => 'Bearer',
                'access_token' => $access->plainTextToken,
                'access_expires_at' => $accessExpiresAt->toIso8601String(),
                'refresh_token' => $rawNext,
                'refresh_expires_at' => $next->expires_at?->toIso8601String(),
                'session_id' => $session->id,
                'user_id' => (int) $user->getKey(),
            ];
        }, 3);

        if (isset($result['error'])) {
            $this->handleSecurityResult($result, $deviceId, $request);
        }

        $this->audit->write(
            'identity.session.refreshed',
            $result['user_id'],
            targetType: 'identity_session',
            targetId: $result['session_id'],
            metadata: ['device_id' => $deviceId],
            request: $request,
        );

        unset($result['user_id']);

        return $result;
    }

    private function revokeFamily(
        IdentityRefreshToken $refresh,
        IdentitySession $session,
        string $reason,
        bool $reuseDetected = false,
    ): void {
        IdentityRefreshToken::query()
            ->where('family_id', $refresh->family_id)
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'reuse_detected_at' => $reuseDetected ? now() : null,
                'updated_at' => now(),
            ]);

        if ($session->access_token_id !== null) {
            DB::table('personal_access_tokens')->where('id', $session->access_token_id)->delete();
        }

        $session->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoke_reason' => $reason,
            'access_token_id' => null,
        ])->save();
    }

    private function handleSecurityResult(array $result, string $deviceId, ?Request $request): never
    {
        $error = (string) $result['error'];

        if (isset($result['user_id'], $result['session_id'])) {
            $this->audit->write(
                'identity.session.security_revocation',
                (int) $result['user_id'],
                targetType: 'identity_session',
                targetId: (string) $result['session_id'],
                metadata: ['device_id' => $deviceId, 'reason' => $error],
                request: $request,
            );
        }

        if ($error === 'reuse' && isset($result['user_id'], $result['family_id'])) {
            if (class_exists(RouteNotificationAction::class)) {
                app(RouteNotificationAction::class)->handle(
                    (int) $result['user_id'],
                    'security.session_revoked',
                    'refresh-reuse:'.(string) $result['family_id'],
                    ['resource_id' => (string) $result['session_id'], 'actor_user_id' => (int) $result['user_id'], 'deep_link' => '/profile/devices'],
                    NotificationPriority::High,
                );
            }

            throw new UnauthorizedHttpException(
                'Bearer',
                'Refresh token reuse was detected. The device session has been revoked.',
            );
        }

        $messages = [
            'invalid' => 'Refresh token is invalid.',
            'inactive' => 'Session is no longer active.',
            'device_mismatch' => 'Refresh token is not valid for this device.',
            'expired' => 'Refresh token has expired.',
        ];

        throw new UnauthorizedHttpException('Bearer', $messages[$error] ?? 'Refresh failed.');
    }
}
