<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\ModerationEnforcement;
use App\Models\ModerationReport;
use App\Models\User;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Admin\Operations\Services\AdminCircleOperationsService;
use App\Modules\Admin\Operations\Services\AdminUserControlService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class ModerationEnforcementService
{
    public function __construct(
        private AdminUserControlService $users,
        private AdminCircleOperationsService $circles,
        private AdminAuditLogger $audit,
        private RouteNotificationAction $notify,
        private ModerationIntakeService $intake,
    ) {}

    /** @param array<string,mixed> $data */
    public function apply(ModerationReport $report, AdminUser $admin, AdminSession $session, array $data, Request $request): ModerationEnforcement
    {
        $action = (string) $data['action'];
        $reason = (string) $data['reason'];
        $targetType = str_contains($action, 'circle') ? 'circle' : 'user';
        $targetId = $targetType === 'user' ? $report->target_user_id : ($report->target_type === 'circle' ? $report->target_id : data_get($report->target_snapshot, 'circle_id'));
        if (! $targetId) {
            throw new ModerationDomainException('ENFORCEMENT_TARGET_INVALID', 'The report does not expose a valid enforcement target.', 422);
        }

        return DB::transaction(function () use ($report, $admin, $session, $data, $request, $action, $reason, $targetType, $targetId) {
            if ($targetType === 'user') {
                $user = User::query()->find((int) $targetId);
                if (! $user) {
                    throw new ModerationDomainException('ENFORCEMENT_TARGET_INVALID', 'The user target no longer exists.', 404);
                }
                $this->applyUser($user, $admin, $session, $action, $data, $reason, $request);
            } else {
                $circle = Circle::query()->find((string) $targetId);
                if (! $circle) {
                    throw new ModerationDomainException('ENFORCEMENT_TARGET_INVALID', 'The Circle target no longer exists.', 404);
                }
                $this->applyCircle($circle, $admin, $session, $action, $reason, $request);
            }

            $enforcement = ModerationEnforcement::query()->create([
                'report_id' => $report->id, 'target_type' => $targetType, 'target_id' => (string) $targetId, 'action' => $action,
                'parameters' => $this->safeParameters($data), 'reason' => $reason, 'admin_user_id' => $admin->id, 'status' => 'applied', 'applied_at' => now(),
            ]);
            $before = $report->status;
            $report->forceFill(['status' => 'actioned', 'actioned_at' => $report->actioned_at ?? now()])->save();
            $this->audit->write('admin.moderation.enforcement.applied', $admin, $session, 'moderation_enforcement', $enforcement->id, reason: $reason, before: ['report_status' => $before], after: ['report_status' => 'actioned', 'action' => $action, 'target_type' => $targetType, 'target_id' => (string) $targetId], request: $request);
            $this->intake->broadcast($report);
            if ($targetType === 'user') {
                $this->notify->handle((int) $targetId, 'generic.enforcement', 'moderation-enforcement:'.$enforcement->id, [
                    'resource_id' => (string) $enforcement->id, 'deep_link' => 'orbit://account',
                ], NotificationPriority::High, null, 'orbit://account');
            }

            return $enforcement;
        });
    }

    private function applyUser(User $user, AdminUser $admin, AdminSession $session, string $action, array $data, string $reason, Request $request): void
    {
        if ($action === 'suspend_user_temp') {
            $duration = (int) ($data['duration_minutes'] ?? 0);
            if ($duration < 5 || $duration > 10080) {
                throw new ModerationDomainException('ENFORCEMENT_DURATION_INVALID', 'Temporary suspension duration must be 5 to 10080 minutes.', 422);
            }
            $this->users->suspend($user, $admin, $session, $reason, $duration, $request);

            return;
        }
        if ($action === 'suspend_user_indefinite') {
            $this->users->suspend($user, $admin, $session, $reason, null, $request);

            return;
        }
        if ($action === 'restore_user') {
            $this->users->reactivate($user, $admin, $session, $reason, $request);

            return;
        }

        $control = $this->users->controlFor($user);
        $features = $control->feature_restrictions ?? [];
        $warning = $control->warning;
        if ($action === 'restrict_user_feature') {
            $feature = (string) ($data['feature'] ?? '');
            if (! in_array($feature, (array) config('orbit_admin_operations.allowed_user_features', []), true)) {
                throw new ModerationDomainException('ENFORCEMENT_FEATURE_INVALID', 'Unsupported feature restriction.', 422);
            }
            $features[] = $feature;
        } elseif ($action === 'warn_user') {
            $warning = (string) ($data['warning'] ?? $reason);
        } else {
            throw new ModerationDomainException('ENFORCEMENT_ACTION_INVALID', 'Unsupported user enforcement action.', 422);
        }
        $this->users->updateControls(
            $user, $admin, $session, array_values(array_unique($features)), $control->rate_limit_per_minute,
            (bool) $control->require_reverification, $control->risk_level, $warning, $control->trust_safety_escalated_at !== null, $reason, $request
        );
    }

    private function applyCircle(Circle $circle, AdminUser $admin, AdminSession $session, string $action, string $reason, Request $request): void
    {
        $status = match ($action) {
            'freeze_circle' => 'frozen','restore_circle' => 'normal','remove_circle' => 'removed',
            default => throw new ModerationDomainException('ENFORCEMENT_ACTION_INVALID', 'Unsupported Circle enforcement action.', 422),
        };
        try {
            $this->circles->setStatus($circle, $admin, $session, $status, $reason, $request);
        } catch (\DomainException $e) {
            throw new ModerationDomainException('ENFORCEMENT_CONFLICT', $e->getMessage(), 409);
        }
    }

    private function safeParameters(array $data): array
    {
        return array_filter([
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'feature' => $data['feature'] ?? null,
            'warning' => $data['warning'] ?? null,
        ],fn ($v) => $v !== null);
    }
}
