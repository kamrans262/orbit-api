<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Services;

use App\Models\AccountDeletionRequest;
use App\Models\AdminRecordNote;
use App\Models\AdminRecordTag;
use App\Models\AdminUserControl;
use App\Models\DataExportRequest;
use App\Models\IdentitySession;
use App\Models\SecurityAuditLog;
use App\Models\SosEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class AdminUserOperationsPresenter
{
    public function __construct(
        private AdminUserControlService $controls,
        private AdminDeviceOperationsService $devices,
    ) {}

    /** @return array<string,mixed> */
    public function summary(User $user): array
    {
        $control = $user->relationLoaded('adminOperationalControl')
            ? $user->getRelation('adminOperationalControl')
            : AdminUserControl::query()->whereKey($user->id)->first();
        $status = $user->getAttribute('account_deleted_at') !== null
            ? 'deleted'
            : ($control ? $this->controls->effectiveStatus($control) : 'active');

        return [
            'id' => $user->id,
            'display_name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'account_status' => $status,
            'registered_at' => $user->created_at?->toIso8601String(),
            'last_active_at' => $this->lastActiveAt($user),
            'circle_count' => $this->aggregateCount($user, 'admin_circle_count', 'circle_members', 'user_id'),
            'device_count' => $this->aggregateCount($user, 'admin_device_count', 'devices', 'user_id'),
            'active_device_count' => $this->activeDeviceCount($user),
            'sos_count' => $this->aggregateCount($user, 'admin_sos_count', 'sos_events', 'user_id'),
            'risk_level' => $control?->risk_level ?? 'normal',
            'require_reverification' => (bool) ($control?->require_reverification ?? false),
            'deletion_state' => $user->getAttribute('account_deleted_at') !== null ? 'completed' : ($user->getAttribute('account_deletion_scheduled_for') !== null ? 'scheduled' : 'none'),
        ];
    }

    /** @return array<string,mixed> */
    public function detail(User $user): array
    {
        $control = AdminUserControl::query()->whereKey($user->id)->first();
        $memberships = DB::table('circle_members')
            ->join('circles', 'circles.id', '=', 'circle_members.circle_id')
            ->where('circle_members.user_id', $user->id)
            ->orderByDesc('circle_members.joined_at')
            ->limit(100)
            ->get([
                'circle_members.id as membership_id', 'circle_members.circle_id', 'circles.name as circle_name',
                'circle_members.role', 'circle_members.location_mode', 'circle_members.can_ping',
                'circle_members.can_message', 'circle_members.can_view_moments', 'circle_members.activity_visibility',
                'circle_members.joined_at', 'circles.archived_at',
            ])->map(fn ($row): array => [
                'membership_id' => $row->membership_id,
                'circle_id' => $row->circle_id,
                'circle_name' => $row->circle_name,
                'role' => $row->role,
                'location_mode' => $row->location_mode,
                'can_ping' => (bool) $row->can_ping,
                'can_message' => (bool) $row->can_message,
                'can_view_moments' => (bool) $row->can_view_moments,
                'activity_visibility' => (bool) $row->activity_visibility,
                'joined_at' => $row->joined_at,
                'circle_archived' => $row->archived_at !== null,
            ])->all();

        $presence = DB::table('presence_states')->where('user_id', $user->id)->first();
        $deletion = AccountDeletionRequest::query()->where('user_id', $user->id)->latest('requested_at')->first();
        $export = DataExportRequest::query()->where('user_id', $user->id)->latest('requested_at')->first();

        return [
            ...$this->summary($user),
            'timezone' => $user->timezone,
            'locale' => $user->locale,
            'global_ghost_mode' => (bool) $user->global_ghost_mode,
            'controls' => $control ? $this->controls->snapshot($control) : [
                'status' => 'active', 'suspended_until' => null, 'feature_restrictions' => [],
                'rate_limit_per_minute' => null, 'require_reverification' => false, 'risk_level' => 'normal',
                'warning' => null, 'trust_safety_escalated_at' => null,
            ],
            'presence_operations' => $presence ? [
                'status' => $presence->status,
                'reported_at' => $presence->reported_at,
                'location_updated_at' => $presence->location_updated_at,
                'network_type' => $presence->network_type,
                'battery_level' => $presence->battery_level,
                'is_charging' => $presence->is_charging !== null ? (bool) $presence->is_charging : null,
                'has_location_sample' => $presence->latitude !== null && $presence->longitude !== null,
            ] : null,
            'devices' => $user->devices()->latest('last_seen_at')->get()->map(fn ($device): array => $this->devices->present($device))->all(),
            'sessions' => IdentitySession::query()->where('user_id', $user->id)->latest('created_at')->limit(50)->get()->map(fn (IdentitySession $session): array => [
                'id' => $session->id,
                'device_id' => $session->device_id,
                'status' => $session->status,
                'last_seen_at' => $session->last_seen_at?->toIso8601String(),
                'access_expires_at' => $session->access_expires_at?->toIso8601String(),
                'refresh_expires_at' => $session->refresh_expires_at?->toIso8601String(),
                'revoked_at' => $session->revoked_at?->toIso8601String(),
                'revoke_reason' => $session->revoke_reason,
                'created_at' => $session->created_at?->toIso8601String(),
            ])->all(),
            'circle_memberships' => $memberships,
            'recent_security_events' => SecurityAuditLog::query()->where('user_id', $user->id)->latest('occurred_at')->limit(20)->get()->map(fn (SecurityAuditLog $log): array => [
                'id' => $log->id, 'action' => $log->action, 'target_type' => $log->target_type,
                'target_id' => $log->target_id, 'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])->all(),
            'recent_sos' => SosEvent::query()->where('user_id', $user->id)->latest('activated_at')->limit(10)->get()->map(fn (SosEvent $sos): array => [
                'id' => $sos->id, 'circle_id' => $sos->circle_id, 'status' => $sos->status,
                'escalation_stage' => $sos->escalation_stage, 'activated_at' => $sos->activated_at?->toIso8601String(),
                'resolved_at' => $sos->resolved_at?->toIso8601String(),
            ])->all(),
            'privacy' => [
                'latest_deletion' => $deletion ? [
                    'id' => $deletion->id, 'status' => $deletion->status,
                    'requested_at' => $deletion->requested_at?->toIso8601String(),
                    'scheduled_for' => $deletion->scheduled_for?->toIso8601String(),
                    'completed_at' => $deletion->completed_at?->toIso8601String(),
                ] : null,
                'latest_export' => $export ? [
                    'id' => $export->id, 'status' => $export->status,
                    'requested_at' => $export->requested_at?->toIso8601String(),
                    'completed_at' => $export->completed_at?->toIso8601String(),
                    'expires_at' => $export->expires_at?->toIso8601String(),
                ] : null,
            ],
            'notes' => AdminRecordNote::query()->where('target_type', 'user')->where('target_id', (string) $user->id)->latest('created_at')->limit(100)->get()->map(fn ($note): array => [
                'id' => $note->id, 'note' => $note->note, 'admin_user_id' => $note->admin_user_id,
                'created_at' => $note->created_at?->toIso8601String(),
            ])->all(),
            'tags' => AdminRecordTag::query()->where('target_type', 'user')->where('target_id', (string) $user->id)->orderBy('tag')->get()->map(fn ($tag): array => [
                'id' => $tag->id, 'tag' => $tag->tag, 'admin_user_id' => $tag->admin_user_id,
                'created_at' => $tag->created_at?->toIso8601String(),
            ])->all(),
            'subscription' => null,
            'support_history' => [],
            'moderation_history' => [],
        ];
    }

    private function lastActiveAt(User $user): ?string
    {
        $attributes = $user->getAttributes();
        if (array_key_exists('admin_last_device_seen_at', $attributes) || array_key_exists('admin_last_session_seen_at', $attributes)) {
            $value = collect([
                $attributes['admin_last_device_seen_at'] ?? null,
                $attributes['admin_last_session_seen_at'] ?? null,
            ])->filter()->sortDesc()->first();

            return $value !== null ? (string) $value : null;
        }

        $device = DB::table('devices')->where('user_id', $user->id)->max('last_seen_at');
        $session = DB::table('identity_sessions')->where('user_id', $user->id)->max('last_seen_at');
        $value = collect([$device, $session])->filter()->sortDesc()->first();

        return $value !== null ? (string) $value : null;
    }

    private function aggregateCount(User $user, string $attribute, string $table, string $userColumn): int
    {
        $attributes = $user->getAttributes();
        if (array_key_exists($attribute, $attributes)) {
            return (int) $attributes[$attribute];
        }

        return (int) DB::table($table)->where($userColumn, $user->id)->count();
    }

    private function activeDeviceCount(User $user): int
    {
        $attributes = $user->getAttributes();
        if (array_key_exists('admin_active_device_count', $attributes)) {
            return (int) $attributes['admin_active_device_count'];
        }

        return (int) DB::table('devices')->where('user_id', $user->id)->whereNull('revoked_at')->count();
    }
}
