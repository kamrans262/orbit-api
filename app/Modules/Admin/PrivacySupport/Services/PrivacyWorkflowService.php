<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\PrivacyRequest;
use App\Modules\Admin\PrivacySupport\Exceptions\PrivacySupportDomainException;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Http\Request;

final readonly class PrivacyWorkflowService
{
    public function __construct(
        private AdminAuditLogger $audit,
        private ContactHistoryService $contacts,
    ) {}

    public function assign(
        PrivacyRequest $privacy,
        ?AdminUser $assignee,
        AdminUser $actor,
        AdminSession $session,
        string $reason,
        Request $request,
    ): PrivacyRequest {
        if ($assignee !== null && (! $assignee->isOperationallyActive() || ! $assignee->hasPermission('privacy.view'))) {
            throw new PrivacySupportDomainException('PRIVACY_ASSIGNEE_INVALID', 422, 'The selected administrator is not eligible for privacy work.');
        }

        $before = ['assigned_admin_id' => $privacy->assigned_admin_id];
        $privacy->forceFill(['assigned_admin_id' => $assignee?->id])->save();

        $this->audit->write(
            'admin.privacy.assignment.updated', $actor, $session, 'privacy_request', $privacy->id,
            reason: $reason, before: $before, after: ['assigned_admin_id' => $privacy->assigned_admin_id], request: $request,
        );

        return $privacy->refresh();
    }

    public function update(
        PrivacyRequest $privacy,
        array $data,
        AdminUser $actor,
        AdminSession $session,
        Request $request,
    ): PrivacyRequest {
        $allowed = ['new', 'verification_required', 'verified', 'in_progress', 'waiting_user', 'completed', 'rejected', 'cancelled'];
        $status = (string) $data['status'];

        if (! in_array($status, $allowed, true)) {
            throw new PrivacySupportDomainException('PRIVACY_STATUS_INVALID', 422, 'Unsupported privacy request status.');
        }

        if (in_array($privacy->status, ['completed', 'rejected', 'cancelled'], true) && $status !== $privacy->status) {
            throw new PrivacySupportDomainException('PRIVACY_REQUEST_FINAL', 409, 'A finalized privacy request cannot be reopened.');
        }

        if ($status === 'completed' && empty($data['resolution'])) {
            throw new PrivacySupportDomainException('PRIVACY_RESOLUTION_REQUIRED', 422, 'A completion resolution is required.');
        }

        $before = [
            'status' => $privacy->status,
            'resolution' => $privacy->resolution,
            'deadline_at' => $privacy->deadline_at?->toIso8601String(),
        ];

        $privacy->forceFill([
            'status' => $status,
            'resolution' => $data['resolution'] ?? $privacy->resolution,
            'deadline_at' => array_key_exists('deadline_at', $data) ? $data['deadline_at'] : $privacy->deadline_at,
            'completed_at' => in_array($status, ['completed', 'rejected', 'cancelled'], true) ? now() : null,
        ])->save();

        $after = [
            'status' => $privacy->status,
            'resolution' => $privacy->resolution,
            'deadline_at' => $privacy->deadline_at?->toIso8601String(),
        ];

        $this->audit->write(
            'admin.privacy.workflow.updated', $actor, $session, 'privacy_request', $privacy->id,
            reason: (string) $data['reason'], before: $before, after: $after, request: $request,
        );

        if (in_array($status, ['completed', 'rejected'], true)) {
            $this->contacts->record(
                (int) $privacy->user_id,
                'privacy.request.'.$status,
                'system',
                'outbound',
                'Privacy request update',
                'Your Orbit privacy request status was updated.',
                'privacy_request',
                $privacy->id,
                $actor,
                ['status' => $status],
            );

            if (class_exists(RouteNotificationAction::class)) {
                app(RouteNotificationAction::class)->handle(
                    (int) $privacy->user_id,
                    'privacy.request_'.$status,
                    'privacy-request:'.$privacy->id.':'.$status,
                    ['resource_id' => $privacy->id, 'actor_user_id' => (int) $privacy->user_id, 'deep_link' => '/profile/privacy'],
                    NotificationPriority::Normal,
                );
            }
        }

        return $privacy->refresh();
    }

    public function verifyIdentity(
        PrivacyRequest $privacy,
        string $method,
        AdminUser $actor,
        AdminSession $session,
        string $reason,
        Request $request,
    ): PrivacyRequest {
        $before = ['identity_status' => $privacy->identity_status];
        $privacy->forceFill([
            'identity_status' => 'verified',
            'status' => $privacy->status === 'verification_required' ? 'verified' : $privacy->status,
        ])->save();

        $this->audit->write(
            'admin.privacy.identity.verified', $actor, $session, 'privacy_request', $privacy->id,
            reason: $reason, before: $before, after: ['identity_status' => 'verified'],
            metadata: ['verification_method' => $method], request: $request,
        );

        return $privacy->refresh();
    }
}
