<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Services;

use App\Models\AdminSosIncidentControl;
use App\Models\AdminSosNote;
use App\Models\SosEvent;
use Illuminate\Support\Facades\DB;

final class AdminSosPresenter
{
    /** @return array<string,mixed> */
    public function summary(SosEvent $incident): array
    {
        $control = $this->control($incident);
        $originator = $incident->relationLoaded('originator') ? $incident->originator : null;
        $circle = $incident->relationLoaded('circle') ? $incident->circle : null;

        return [
            'id' => $incident->id,
            'status' => $incident->status,
            'activation_time' => $incident->activated_at?->toIso8601String(),
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'elapsed_seconds' => $incident->activated_at?->diffInSeconds($incident->resolved_at ?? now()),
            'escalation_stage' => (int) $incident->escalation_stage,
            'user' => [
                'id' => (int) $incident->user_id,
                'display_name' => $originator?->name,
                'email' => $originator?->email,
            ],
            'circle' => [
                'id' => $incident->circle_id,
                'name' => $circle?->name,
            ],
            'assigned_admin' => $control?->assignedAdmin ? [
                'id' => (int) $control->assignedAdmin->id,
                'name' => $control->assignedAdmin->name,
            ] : null,
            'operational_status' => $control?->operational_status ?? 'open',
            'internal_escalation_level' => $control?->internal_escalation_level ?? 'normal',
            'false_alarm' => (bool) ($control?->false_alarm ?? false),
            'technical_failure' => (bool) ($control?->technical_failure ?? false),
            'abuse_flag' => (bool) ($control?->abuse_flag ?? false),
            'location_update_health' => $this->locationHealth($incident),
            'recording_upload_health' => $this->recordingHealth($incident),
            'network_health' => [
                'status' => 'unknown',
                'source' => 'client_network_telemetry_not_collected',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function detail(SosEvent $incident): array
    {
        $responders = DB::table('sos_responders')
            ->leftJoin('users', 'users.id', '=', 'sos_responders.user_id')
            ->where('sos_responders.sos_event_id', $incident->id)
            ->orderBy('sos_responders.created_at')
            ->get([
                'sos_responders.id',
                'sos_responders.user_id',
                'users.name as display_name',
                'sos_responders.status',
                'sos_responders.engaged_at',
                'sos_responders.responded_at',
                'sos_responders.last_location_at',
            ])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'user_id' => (int) $row->user_id,
                'display_name' => $row->display_name,
                'status' => $row->status,
                'engaged_at' => $row->engaged_at,
                'responded_at' => $row->responded_at,
                'last_location_at' => $row->last_location_at,
            ])->all();

        $escalations = DB::table('sos_escalations')
            ->where('sos_event_id', $incident->id)
            ->orderBy('stage')
            ->get(['stage', 'action', 'status', 'context', 'occurred_at'])
            ->map(fn ($row): array => [
                'stage' => (int) $row->stage,
                'action' => $row->action,
                'status' => $row->status,
                'context' => is_string($row->context) ? json_decode($row->context, true) : $row->context,
                'occurred_at' => $row->occurred_at,
            ])->all();

        $outbox = DB::table('sos_notification_outbox')
            ->where('sos_event_id', $incident->id)
            ->orderBy('created_at')
            ->get(['id', 'target_user_id', 'channel', 'kind', 'priority', 'status', 'available_at', 'delivered_at', 'attempts']);

        $notificationIds = $outbox->isEmpty()
            ? collect()
            : DB::table('orbit_notifications')
                ->whereIn('idempotency_key', $outbox->map(fn ($row): string => 'sos-outbox:'.$row->id)->all())
                ->pluck('id');

        $deliveries = $notificationIds->isEmpty()
            ? collect()
            : DB::table('notification_deliveries')
                ->whereIn('notification_id', $notificationIds)
                ->get(['notification_id', 'device_id', 'provider', 'priority', 'silent', 'status', 'dispatched_at', 'attempts']);

        $notes = AdminSosNote::query()
            ->where('sos_event_id', $incident->id)
            ->with('admin:id,name')
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(fn (AdminSosNote $note): array => [
                'id' => $note->id,
                'admin' => $note->admin ? ['id' => (int) $note->admin->id, 'name' => $note->admin->name] : null,
                'note' => $note->note,
                'created_at' => $note->created_at?->toIso8601String(),
            ])->all();

        return [
            ...$this->summary($incident),
            'operational_resolution' => $this->control($incident)?->operational_resolution,
            'responders' => $responders,
            'escalations' => $escalations,
            'notification_pipeline' => [
                'outbox' => $outbox->map(fn ($row): array => [
                    'id' => $row->id,
                    'target_user_id' => (int) $row->target_user_id,
                    'channel' => $row->channel,
                    'kind' => $row->kind,
                    'priority' => $row->priority,
                    'status' => $row->status,
                    'available_at' => $row->available_at,
                    'delivered_at' => $row->delivered_at,
                    'attempts' => (int) $row->attempts,
                ])->all(),
                'provider_deliveries' => $deliveries->map(fn ($row): array => [
                    'notification_id' => $row->notification_id,
                    'device_id_masked' => $this->maskIdentifier((string) $row->device_id),
                    'provider' => $row->provider,
                    'priority' => $row->priority,
                    'silent' => (bool) $row->silent,
                    'status' => $row->status,
                    'dispatched_at' => $row->dispatched_at,
                    'attempts' => (int) $row->attempts,
                ])->all(),
            ],
            'notes' => $notes,
            'sensitive_access_count' => (int) DB::table('admin_sos_sensitive_access_logs')->where('sos_event_id', $incident->id)->count(),
        ];
    }

    /** @return array<string,mixed> */
    public function exportSnapshot(SosEvent $incident): array
    {
        $detail = $this->detail($incident);

        unset($detail['notes'], $detail['sensitive_access_count']);

        return [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'privacy' => [
                'contains_precise_location' => false,
                'contains_recording_reference' => false,
                'contains_plaintext_private_content' => false,
            ],
            'incident' => $detail,
        ];
    }

    private function control(SosEvent $incident): ?AdminSosIncidentControl
    {
        if ($incident->relationLoaded('adminSafetyControl')) {
            return $incident->getRelation('adminSafetyControl');
        }

        return AdminSosIncidentControl::query()
            ->with('assignedAdmin:id,name')
            ->whereKey($incident->id)
            ->first();
    }

    /** @return array<string,mixed> */
    private function locationHealth(SosEvent $incident): array
    {
        if ($incident->last_location_at === null) {
            return ['status' => 'never_reported', 'last_update_at' => null];
        }

        $age = $incident->last_location_at->diffInSeconds(now());

        return [
            'status' => $age <= 30 ? 'healthy' : ($age <= 120 ? 'delayed' : 'stale'),
            'last_update_at' => $incident->last_location_at->toIso8601String(),
            'age_seconds' => $age,
        ];
    }

    /** @return array<string,mixed> */
    private function recordingHealth(SosEvent $incident): array
    {
        if ($incident->recording_ref === null) {
            return ['status' => 'not_attached', 'expires_at' => null];
        }

        return [
            'status' => $incident->recording_expires_at?->isPast() ? 'expired_reference' : 'encrypted_reference_present',
            'expires_at' => $incident->recording_expires_at?->toIso8601String(),
        ];
    }

    private function maskIdentifier(string $value): string
    {
        if (mb_strlen($value) <= 8) {
            return '••••';
        }

        return mb_substr($value, 0, 4).'••••'.mb_substr($value, -4);
    }
}
