<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\AdminUserControl;
use App\Models\User;
use App\Modules\Admin\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class AdminUserControlService
{
    public function __construct(
        private AdminConsumerSessionService $sessions,
        private AdminAuditLogger $audit,
    ) {}

    public function controlFor(User $user): AdminUserControl
    {
        return AdminUserControl::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['status' => 'active', 'risk_level' => 'normal'],
        );
    }

    public function suspend(User $user, AdminUser $admin, AdminSession $session, string $reason, ?int $durationMinutes, Request $request): AdminUserControl
    {
        return DB::transaction(function () use ($user, $admin, $session, $reason, $durationMinutes, $request): AdminUserControl {
            $control = $this->controlFor($user);
            $before = $this->snapshot($control);
            $control->forceFill([
                'status' => 'suspended',
                'suspended_until' => $durationMinutes !== null ? now()->addMinutes($durationMinutes) : null,
                'suspension_reason' => $reason,
                'updated_by_admin_id' => $admin->id,
            ])->save();
            $revoked = $this->sessions->revokeAll($user, 'admin_suspension');
            $this->audit->write(
                'admin.user.suspended', $admin, $session, 'user', $user->id,
                reason: $reason, before: $before, after: $this->snapshot($control),
                metadata: ['duration_minutes' => $durationMinutes, 'revoked' => $revoked], request: $request,
            );

            return $control->refresh();
        });
    }

    public function reactivate(User $user, AdminUser $admin, AdminSession $session, string $reason, Request $request): AdminUserControl
    {
        return DB::transaction(function () use ($user, $admin, $session, $reason, $request): AdminUserControl {
            $control = $this->controlFor($user);
            $before = $this->snapshot($control);
            $control->forceFill([
                'status' => 'active', 'suspended_until' => null, 'suspension_reason' => null,
                'updated_by_admin_id' => $admin->id,
            ])->save();
            $this->audit->write(
                'admin.user.reactivated', $admin, $session, 'user', $user->id,
                reason: $reason, before: $before, after: $this->snapshot($control), request: $request,
            );

            return $control->refresh();
        });
    }

    /** @param list<string> $featureRestrictions */
    public function updateControls(
        User $user,
        AdminUser $admin,
        AdminSession $session,
        array $featureRestrictions,
        ?int $rateLimit,
        bool $requireReverification,
        string $riskLevel,
        ?string $warning,
        bool $escalateTrustSafety,
        string $reason,
        Request $request,
    ): AdminUserControl {
        return DB::transaction(function () use ($user, $admin, $session, $featureRestrictions, $rateLimit, $requireReverification, $riskLevel, $warning, $escalateTrustSafety, $reason, $request): AdminUserControl {
            $control = $this->controlFor($user);
            $before = $this->snapshot($control);
            $wasReverificationRequired = $control->require_reverification;
            $control->forceFill([
                'feature_restrictions' => array_values(array_unique($featureRestrictions)),
                'rate_limit_per_minute' => $rateLimit,
                'require_reverification' => $requireReverification,
                'risk_level' => $riskLevel,
                'warning' => $warning,
                'trust_safety_escalated_at' => $escalateTrustSafety ? ($control->trust_safety_escalated_at ?? now()) : null,
                'updated_by_admin_id' => $admin->id,
            ])->save();

            $revoked = null;
            if ($requireReverification && ! $wasReverificationRequired) {
                $revoked = $this->sessions->revokeAll($user, 'admin_reverification_required');
            }

            $this->audit->write(
                'admin.user.controls.updated', $admin, $session, 'user', $user->id,
                reason: $reason, before: $before, after: $this->snapshot($control),
                metadata: ['revoked' => $revoked], request: $request,
            );

            return $control->refresh();
        });
    }

    public function effectiveStatus(AdminUserControl $control): string
    {
        if ($control->status !== 'suspended') {
            return 'active';
        }

        if ($control->suspended_until !== null && $control->suspended_until->isPast()) {
            return 'active';
        }

        return 'suspended';
    }

    /** @return array<string,mixed> */
    public function snapshot(AdminUserControl $control): array
    {
        return [
            'status' => $this->effectiveStatus($control),
            'suspended_until' => $control->suspended_until?->toIso8601String(),
            'feature_restrictions' => $control->feature_restrictions ?? [],
            'rate_limit_per_minute' => $control->rate_limit_per_minute,
            'require_reverification' => (bool) $control->require_reverification,
            'risk_level' => $control->risk_level,
            'warning' => $control->warning,
            'trust_safety_escalated_at' => $control->trust_safety_escalated_at?->toIso8601String(),
        ];
    }
}
