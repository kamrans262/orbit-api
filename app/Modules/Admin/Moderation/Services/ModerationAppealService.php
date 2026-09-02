<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\ModerationAppeal;
use App\Models\ModerationEnforcement;
use App\Models\User;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Admin\Operations\Services\AdminUserControlService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class ModerationAppealService
{
    public function __construct(
        private AdminUserControlService $controls,
        private AdminAuditLogger $audit,
        private RouteNotificationAction $notify,
    ) {}

    public function submit(User $user, string $enforcementId, string $explanation): ModerationAppeal
    {
        $enforcement = ModerationEnforcement::query()->find($enforcementId);
        if (! $enforcement || $enforcement->target_type !== 'user' || (int) $enforcement->target_id !== (int) $user->id) {
            throw new ModerationDomainException('APPEAL_ENFORCEMENT_UNAVAILABLE', 'The enforcement is unavailable for appeal.', 404);
        }

        $existing = ModerationAppeal::query()->where('enforcement_id', $enforcement->id)->first();
        if ($existing) {
            return $existing;
        }

        return ModerationAppeal::query()->create([
            'enforcement_id' => $enforcement->id,
            'user_id' => $user->id,
            'explanation' => $explanation,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function assign(
        ModerationAppeal $appeal,
        ?AdminUser $assignee,
        AdminUser $admin,
        AdminSession $session,
        string $reason,
        Request $request,
    ): ModerationAppeal {
        if ($assignee && (! $assignee->isOperationallyActive() || ! $assignee->hasPermission('appeals.review'))) {
            throw new ModerationDomainException('APPEAL_ASSIGNEE_INVALID', 'The selected administrator cannot review appeals.', 422);
        }

        $before = ['assigned_admin_id' => $appeal->assigned_admin_id, 'status' => $appeal->status];
        $appeal->forceFill([
            'assigned_admin_id' => $assignee?->id,
            'status' => $assignee && $appeal->status === 'submitted' ? 'under_review' : $appeal->status,
        ])->save();

        $this->audit->write(
            'admin.moderation.appeal.assigned',
            $admin,
            $session,
            'moderation_appeal',
            $appeal->id,
            reason: $reason,
            before: $before,
            after: ['assigned_admin_id' => $appeal->assigned_admin_id, 'status' => $appeal->status],
            request: $request,
        );

        return $appeal->refresh();
    }

    public function review(
        ModerationAppeal $appeal,
        AdminUser $admin,
        AdminSession $session,
        string $outcome,
        string $reason,
        ?int $modifiedDuration,
        bool $requireSecondReview,
        Request $request,
    ): ModerationAppeal {
        if ($appeal->status === 'decided' || $appeal->status === 'second_review') {
            throw new ModerationDomainException('APPEAL_REVIEW_STATE_INVALID', 'This appeal is not available for a first review.', 409);
        }

        $enforcement = $appeal->enforcement()->firstOrFail();
        $needsSecond = $requireSecondReview || $enforcement->action === 'suspend_user_indefinite';

        if ($outcome === 'modified') {
            $this->validateModification($enforcement, $modifiedDuration);
        }

        if ($needsSecond) {
            $appeal->forceFill([
                'status' => 'second_review',
                'assigned_admin_id' => $appeal->assigned_admin_id ?? $admin->id,
                'outcome' => $outcome,
                'decision_reason' => $reason,
                'reviewer_admin_id' => $admin->id,
                'requires_second_review' => true,
                'review_metadata' => ['modified_duration_minutes' => $modifiedDuration],
                'reviewed_at' => now(),
            ])->save();

            $this->audit->write(
                'admin.moderation.appeal.second_review.requested',
                $admin,
                $session,
                'moderation_appeal',
                $appeal->id,
                reason: $reason,
                after: ['proposed_outcome' => $outcome, 'enforcement_id' => (string) $enforcement->id],
                request: $request,
            );

            return $appeal->refresh();
        }

        return $this->finalize($appeal, $enforcement, $admin, $session, $outcome, $reason, $modifiedDuration, $request);
    }

    public function secondReview(
        ModerationAppeal $appeal,
        AdminUser $admin,
        AdminSession $session,
        bool $approved,
        string $reason,
        Request $request,
    ): ModerationAppeal {
        if ($appeal->status !== 'second_review' || ! $appeal->requires_second_review) {
            throw new ModerationDomainException('APPEAL_SECOND_REVIEW_INVALID', 'This appeal is not awaiting a second review.', 409);
        }

        if ((int) $appeal->reviewer_admin_id === (int) $admin->id) {
            throw new ModerationDomainException('APPEAL_SECOND_REVIEW_SEPARATION_REQUIRED', 'A different administrator must perform the second review.', 409);
        }

        if (! $approved) {
            $appeal->forceFill([
                'status' => 'under_review',
                'outcome' => null,
                'decision_reason' => null,
                'requires_second_review' => false,
                'review_metadata' => null,
                'second_reviewer_admin_id' => $admin->id,
                'second_reviewed_at' => now(),
            ])->save();

            $this->audit->write(
                'admin.moderation.appeal.second_review.rejected',
                $admin,
                $session,
                'moderation_appeal',
                $appeal->id,
                reason: $reason,
                after: ['status' => 'under_review'],
                request: $request,
            );

            return $appeal->refresh();
        }

        $outcome = (string) $appeal->outcome;
        $decisionReason = (string) $appeal->decision_reason;
        $enforcement = $appeal->enforcement()->firstOrFail();
        $modifiedDuration = is_numeric(data_get($appeal->review_metadata, 'modified_duration_minutes'))
            ? (int) data_get($appeal->review_metadata, 'modified_duration_minutes')
            : null;

        $appeal->second_reviewer_admin_id = $admin->id;
        $appeal->second_reviewed_at = now();
        $appeal->save();

        return $this->finalize(
            $appeal,
            $enforcement,
            $admin,
            $session,
            $outcome,
            $decisionReason.' Second review: '.$reason,
            $modifiedDuration,
            $request,
        );
    }

    private function finalize(
        ModerationAppeal $appeal,
        ModerationEnforcement $enforcement,
        AdminUser $admin,
        AdminSession $session,
        string $outcome,
        string $reason,
        ?int $modifiedDuration,
        Request $request,
    ): ModerationAppeal {
        return DB::transaction(function () use ($appeal, $enforcement, $admin, $session, $outcome, $reason, $modifiedDuration, $request): ModerationAppeal {
            $user = User::query()->findOrFail($appeal->user_id);

            if ($outcome === 'overturned') {
                $this->reverse($enforcement, $user, $admin, $session, $reason, $request);
                $enforcement->forceFill([
                    'status' => 'reversed',
                    'reversed_at' => now(),
                    'reversed_by_admin_id' => $admin->id,
                    'reversal_reason' => $reason,
                ])->save();
            } elseif ($outcome === 'modified') {
                $this->validateModification($enforcement, $modifiedDuration);
                $this->controls->suspend($user, $admin, $session, $reason, $modifiedDuration, $request);
                $parameters = $enforcement->parameters ?? [];
                $parameters['modified_duration_minutes'] = $modifiedDuration;
                $enforcement->forceFill(['status' => 'modified', 'parameters' => $parameters])->save();
            }

            $appeal->forceFill([
                'status' => 'decided',
                'outcome' => $outcome,
                'decision_reason' => $reason,
                'reviewer_admin_id' => $appeal->reviewer_admin_id ?? $admin->id,
                'requires_second_review' => false,
                'review_metadata' => null,
                'reviewed_at' => $appeal->reviewed_at ?? now(),
            ])->save();

            $this->audit->write(
                'admin.moderation.appeal.decided',
                $admin,
                $session,
                'moderation_appeal',
                $appeal->id,
                reason: $reason,
                after: ['outcome' => $outcome, 'enforcement_id' => (string) $enforcement->id],
                request: $request,
            );

            $this->notify->handle(
                (int) $user->id,
                'generic.appeal',
                'moderation-appeal:'.$appeal->id.':decided',
                ['resource_id' => (string) $appeal->id, 'deep_link' => 'orbit://account/appeals'],
                NotificationPriority::High,
                null,
                'orbit://account/appeals',
            );

            return $appeal->refresh();
        });
    }

    private function validateModification(ModerationEnforcement $enforcement, ?int $modifiedDuration): void
    {
        if (! in_array($enforcement->action, ['suspend_user_temp', 'suspend_user_indefinite'], true)
            || $modifiedDuration === null
            || $modifiedDuration < 5
            || $modifiedDuration > 10080) {
            throw new ModerationDomainException(
                'APPEAL_MODIFICATION_INVALID',
                'Modified outcomes currently require a 5 to 10080 minute user suspension duration.',
                422,
            );
        }
    }

    private function reverse(
        ModerationEnforcement $enforcement,
        User $user,
        AdminUser $admin,
        AdminSession $session,
        string $reason,
        Request $request,
    ): void {
        if (in_array($enforcement->action, ['suspend_user_temp', 'suspend_user_indefinite'], true)) {
            $this->controls->reactivate($user, $admin, $session, $reason, $request);

            return;
        }

        $control = $this->controls->controlFor($user);
        $features = $control->feature_restrictions ?? [];
        $warning = $control->warning;

        if ($enforcement->action === 'restrict_user_feature') {
            $feature = (string) data_get($enforcement->parameters, 'feature', '');
            $features = array_values(array_filter($features, fn ($value): bool => $value !== $feature));
        } elseif ($enforcement->action === 'warn_user') {
            $warning = null;
        } else {
            throw new ModerationDomainException('APPEAL_REVERSAL_UNSUPPORTED', 'This enforcement cannot be automatically overturned.', 409);
        }

        $this->controls->updateControls(
            $user,
            $admin,
            $session,
            $features,
            $control->rate_limit_per_minute,
            (bool) $control->require_reverification,
            $control->risk_level,
            $warning,
            $control->trust_safety_escalated_at !== null,
            $reason,
            $request,
        );
    }
}
