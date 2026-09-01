<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Services;

use App\Models\AdminCircleControl;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Modules\Admin\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class AdminCircleOperationsService
{
    public function __construct(private AdminAuditLogger $audit) {}

    public function controlFor(Circle $circle): AdminCircleControl
    {
        return AdminCircleControl::query()->firstOrCreate(['circle_id' => $circle->id], ['status' => 'normal']);
    }

    /** @param list<string> $restrictions */
    public function updateControls(Circle $circle, AdminUser $admin, AdminSession $session, array $restrictions, string $reason, Request $request): AdminCircleControl
    {
        $control = $this->controlFor($circle);
        $before = $this->snapshot($control);
        $control->forceFill([
            'feature_restrictions' => array_values(array_unique($restrictions)),
            'reason' => $reason,
            'updated_by_admin_id' => $admin->id,
        ])->save();
        $this->audit->write(
            'admin.circle.controls.updated', $admin, $session, 'circle', $circle->id,
            reason: $reason, before: $before, after: $this->snapshot($control), request: $request,
        );

        return $control->refresh();
    }

    public function setStatus(Circle $circle, AdminUser $admin, AdminSession $session, string $status, string $reason, Request $request): AdminCircleControl
    {
        return DB::transaction(function () use ($circle, $admin, $session, $status, $reason, $request): AdminCircleControl {
            $control = $this->controlFor($circle);
            $before = ['circle' => $this->circleState($circle), 'control' => $this->snapshot($control)];

            $containment = [];

            if ($status === 'removed' && $this->hasActiveSos($circle)) {
                throw new \DomainException('A Circle with an active SOS incident cannot be removed. Freeze it and resolve the safety incident first.');
            }

            if ($status === 'archived') {
                if ($control->status === 'removed') {
                    throw new \DomainException('A removed Circle must be explicitly returned to normal before archival changes.');
                }
                $control->forceFill(['status' => 'normal', 'frozen_at' => null, 'removed_at' => null, 'reason' => $reason, 'updated_by_admin_id' => $admin->id])->save();
                $circle->forceFill(['archived_at' => $circle->archived_at ?? now()])->save();
            } elseif ($status === 'restored') {
                if ($control->status === 'removed') {
                    throw new \DomainException('A removed Circle must be explicitly returned to normal before restoration.');
                }
                $control->forceFill(['status' => 'normal', 'frozen_at' => null, 'removed_at' => null, 'reason' => $reason, 'updated_by_admin_id' => $admin->id])->save();
                $circle->forceFill(['archived_at' => null])->save();
            } elseif ($status === 'frozen') {
                $control->forceFill(['status' => 'frozen', 'frozen_at' => now(), 'removed_at' => null, 'reason' => $reason, 'updated_by_admin_id' => $admin->id])->save();
            } elseif ($status === 'normal') {
                $control->forceFill(['status' => 'normal', 'frozen_at' => null, 'removed_at' => null, 'reason' => $reason, 'updated_by_admin_id' => $admin->id])->save();
            } elseif ($status === 'removed') {
                $control->forceFill(['status' => 'removed', 'removed_at' => now(), 'frozen_at' => null, 'reason' => $reason, 'updated_by_admin_id' => $admin->id])->save();
                $circle->forceFill(['archived_at' => $circle->archived_at ?? now()])->save();
                $containment = $this->containRemovedCircle($circle);
            }

            $after = ['circle' => $this->circleState($circle->refresh()), 'control' => $this->snapshot($control->refresh())];
            $this->audit->write(
                'admin.circle.status.updated', $admin, $session, 'circle', $circle->id,
                reason: $reason, before: $before, after: $after,
                metadata: ['requested_status' => $status, 'containment' => $containment], request: $request,
            );

            return $control->refresh();
        });
    }

    public function removeMember(Circle $circle, CircleMember $membership, AdminUser $admin, AdminSession $session, string $reason, Request $request): bool
    {
        if ((string) $membership->role->value === 'owner') {
            return false;
        }

        $before = ['membership_id' => $membership->id, 'user_id' => $membership->user_id, 'role' => $membership->role->value];
        $membership->delete();
        $this->audit->write(
            'admin.circle.member.removed', $admin, $session, 'circle_member', $before['membership_id'],
            reason: $reason, before: $before, after: ['removed' => true], metadata: ['circle_id' => $circle->id], request: $request,
        );

        return true;
    }

    /** @return array<string,mixed> */
    public function snapshot(AdminCircleControl $control): array
    {
        return [
            'status' => $control->status,
            'feature_restrictions' => $control->feature_restrictions ?? [],
            'reason' => $control->reason,
            'frozen_at' => $control->frozen_at?->toIso8601String(),
            'removed_at' => $control->removed_at?->toIso8601String(),
        ];
    }

    private function hasActiveSos(Circle $circle): bool
    {
        return DB::table('sos_events')
            ->where('circle_id', $circle->id)
            ->where('status', 'active')
            ->exists();
    }

    /** @return array<string,int> */
    private function containRemovedCircle(Circle $circle): array
    {
        $now = now();
        $revokedInvites = DB::table('circle_invites')
            ->where('circle_id', $circle->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now, 'updated_at' => $now]);

        $pendingPingsExpired = DB::table('pings')
            ->where('circle_id', $circle->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired', 'expires_at' => $now, 'updated_at' => $now]);

        $messagesPurged = DB::table('messages')
            ->where('circle_id', $circle->id)
            ->delete();

        $activityHidden = DB::table('activity_events')
            ->where('circle_id', $circle->id)
            ->whereNull('removed_at')
            ->whereNotIn('event_type', [
                'alert.sos_activated',
                'alert.sos_escalated',
                'alert.sos_resolved',
            ])
            ->update(['removed_at' => $now, 'updated_at' => $now]);

        $notificationIds = DB::table('orbit_notifications')
            ->where('circle_id', $circle->id)
            ->where('kind', 'not like', 'sos.%')
            ->pluck('id');

        $pendingNotificationsCancelled = $notificationIds->isEmpty()
            ? 0
            : DB::table('notification_deliveries')
                ->whereIn('notification_id', $notificationIds)
                ->where('status', 'pending_provider')
                ->update(['status' => 'cancelled_circle_removed', 'updated_at' => $now]);

        $notificationsHidden = DB::table('orbit_notifications')
            ->whereIn('id', $notificationIds)
            ->where('in_app_visible', true)
            ->update(['in_app_visible' => false, 'updated_at' => $now]);

        return [
            'revoked_invites' => $revokedInvites,
            'pending_pings_expired' => $pendingPingsExpired,
            'pending_messages_purged' => $messagesPurged,
            'activity_items_hidden' => $activityHidden,
            'pending_notifications_cancelled' => $pendingNotificationsCancelled,
            'notifications_hidden' => $notificationsHidden,
        ];
    }

    /** @return array<string,mixed> */
    private function circleState(Circle $circle): array
    {
        return ['archived_at' => $circle->archived_at?->toIso8601String(), 'expires_at' => $circle->expires_at?->toIso8601String()];
    }
}
