<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\IdentitySession;
use App\Models\User;
use App\Modules\Identity\Services\AuditLogger;
use App\Modules\Identity\Services\DeviceTrustService;
use App\Modules\Identity\Services\IdentityTokenService;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class IssueIdentitySessionAction
{
    public function __construct(
        private readonly DeviceTrustService $devices,
        private readonly IdentityTokenService $tokens,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, string $deviceId, ?Request $request = null): array
    {
        $device = $this->devices->assertOwnedDevice((int) $user->getKey(), $deviceId);
        $trust = $this->devices->ensureTrustState((int) $user->getKey(), $deviceId);

        if ($trust->status !== 'trusted') {
            $this->audit->write(
                'identity.device.approval_required',
                (int) $user->getKey(),
                targetType: 'device',
                targetId: $deviceId,
                metadata: ['trust_id' => $trust->id],
                request: $request,
            );

            if (class_exists(RouteNotificationAction::class)) {
                app(RouteNotificationAction::class)->handle(
                    (int) $user->getKey(),
                    'security.device_approval_required',
                    'identity-device-approval:'.$trust->id,
                    ['resource_id' => $trust->id, 'actor_user_id' => (int) $user->getKey(), 'deep_link' => '/identity/device-approvals'],
                    NotificationPriority::High,
                );
            }

            throw new ConflictHttpException('Trusted device approval is required before a secure session can be issued.');
        }

        $session = IdentitySession::query()->create([
            'user_id' => $user->getKey(),
            'device_id' => $deviceId,
            'refresh_family_id' => (string) Str::uuid7(),
            'status' => 'active',
            'device_key_fingerprint' => $this->devices->devicePublicKeyFingerprint($device, $this->tokens),
            'last_seen_at' => now(),
        ]);

        $pair = $this->tokens->createPair($user, $session);

        $this->audit->write(
            'identity.session.issued',
            (int) $user->getKey(),
            targetType: 'identity_session',
            targetId: $session->id,
            metadata: ['device_id' => $deviceId],
            request: $request,
        );

        return $pair;
    }
}
