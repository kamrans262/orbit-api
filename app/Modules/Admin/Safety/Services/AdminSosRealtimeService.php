<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Services;

use App\Models\AdminSosIncidentControl;
use App\Models\SosEvent;
use App\Modules\Admin\Safety\Events\AdminSosIncidentUpdated;

final class AdminSosRealtimeService
{
    public function broadcast(SosEvent $incident, string $changeType): void
    {
        $control = AdminSosIncidentControl::query()->whereKey($incident->id)->first();

        AdminSosIncidentUpdated::dispatch([
            'change_type' => $changeType,
            'sos_id' => $incident->id,
            'user_id' => (int) $incident->user_id,
            'circle_id' => $incident->circle_id,
            'status' => $incident->status,
            'escalation_stage' => (int) $incident->escalation_stage,
            'activated_at' => $incident->activated_at?->toIso8601String(),
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'assigned_admin_id' => $control?->assigned_admin_id,
            'operational_status' => $control?->operational_status ?? 'open',
            'internal_escalation_level' => $control?->internal_escalation_level ?? 'normal',
            'location_update_at' => $incident->last_location_at?->toIso8601String(),
            'has_encrypted_recording_reference' => $incident->recording_ref !== null,
        ]);
    }
}
