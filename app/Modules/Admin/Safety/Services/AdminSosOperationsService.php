<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Services;

use App\Models\AdminSession;
use App\Models\AdminSosExport;
use App\Models\AdminSosIncidentControl;
use App\Models\AdminSosNote;
use App\Models\AdminUser;
use App\Models\SosEvent;
use App\Modules\Admin\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class AdminSosOperationsService
{
    public function __construct(
        private AdminAuditLogger $audit,
        private AdminSosPresenter $presenter,
        private AdminSosRealtimeService $realtime,
    ) {}

    public function controlFor(SosEvent $incident): AdminSosIncidentControl
    {
        return AdminSosIncidentControl::query()->firstOrCreate(
            ['sos_event_id' => $incident->id],
            ['operational_status' => 'open', 'internal_escalation_level' => 'normal'],
        );
    }

    public function assign(
        SosEvent $incident,
        AdminUser $actor,
        AdminSession $session,
        ?AdminUser $assignee,
        string $reason,
        Request $request,
    ): AdminSosIncidentControl {
        if ($assignee !== null && (! $assignee->isOperationallyActive() || ! $assignee->hasPermission('sos.manage'))) {
            throw new \DomainException('The selected administrator is not an active SOS operator.');
        }

        $control = $this->controlFor($incident);
        $before = $this->snapshot($control);

        $control->forceFill([
            'assigned_admin_id' => $assignee?->id,
            'updated_by_admin_id' => $actor->id,
        ])->save();

        $this->audit->write(
            'admin.sos.assignment.updated',
            $actor,
            $session,
            'sos_event',
            $incident->id,
            reason: $reason,
            before: $before,
            after: $this->snapshot($control->refresh()),
            request: $request,
        );
        $this->realtime->broadcast($incident->refresh(), 'admin.sos.assignment.updated');

        return $control->refresh();
    }

    /** @param array<string,mixed> $changes */
    public function updateClassification(
        SosEvent $incident,
        AdminUser $admin,
        AdminSession $session,
        array $changes,
        string $reason,
        Request $request,
    ): AdminSosIncidentControl {
        return DB::transaction(function () use ($incident, $admin, $session, $changes, $reason, $request): AdminSosIncidentControl {
            $control = $this->controlFor($incident);
            $before = $this->snapshot($control);

            if (($changes['operational_status'] ?? null) === 'closed' && blank($changes['operational_resolution'] ?? $control->operational_resolution)) {
                throw new \DomainException('Operational resolution is required before closing an SOS incident.');
            }

            $control->forceFill([
                ...array_intersect_key($changes, array_flip([
                    'operational_status',
                    'internal_escalation_level',
                    'false_alarm',
                    'technical_failure',
                    'abuse_flag',
                    'operational_resolution',
                ])),
                'updated_by_admin_id' => $admin->id,
            ])->save();

            $this->audit->write(
                'admin.sos.classification.updated',
                $admin,
                $session,
                'sos_event',
                $incident->id,
                reason: $reason,
                before: $before,
                after: $this->snapshot($control->refresh()),
                request: $request,
            );
            $this->realtime->broadcast($incident->refresh(), 'admin.sos.classification.updated');

            return $control->refresh();
        });
    }

    public function addNote(
        SosEvent $incident,
        AdminUser $admin,
        AdminSession $session,
        string $noteText,
        Request $request,
    ): AdminSosNote {
        $note = AdminSosNote::query()->create([
            'sos_event_id' => $incident->id,
            'admin_user_id' => $admin->id,
            'note' => $noteText,
            'created_at' => now(),
        ]);

        $this->audit->write(
            'admin.sos.note.created',
            $admin,
            $session,
            'sos_event',
            $incident->id,
            metadata: ['note_id' => $note->id],
            request: $request,
        );
        $this->realtime->broadcast($incident->refresh(), 'admin.sos.note.created');

        return $note;
    }

    public function createExport(
        SosEvent $incident,
        AdminUser $admin,
        AdminSession $session,
        string $format,
        string $reason,
        Request $request,
    ): AdminSosExport {
        $snapshot = $this->presenter->exportSnapshot($incident);

        $export = AdminSosExport::query()->create([
            'sos_event_id' => $incident->id,
            'requested_by_admin_id' => $admin->id,
            'format' => $format,
            'status' => 'ready',
            'snapshot' => $snapshot,
            'requested_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->audit->write(
            'admin.sos.export.created',
            $admin,
            $session,
            'sos_event',
            $incident->id,
            reason: $reason,
            metadata: [
                'export_id' => $export->id,
                'format' => $format,
                'contains_precise_location' => false,
                'contains_recording_reference' => false,
            ],
            request: $request,
        );

        return $export;
    }

    /** @return array<string,mixed> */
    public function snapshot(AdminSosIncidentControl $control): array
    {
        return [
            'assigned_admin_id' => $control->assigned_admin_id,
            'operational_status' => $control->operational_status,
            'internal_escalation_level' => $control->internal_escalation_level,
            'false_alarm' => (bool) $control->false_alarm,
            'technical_failure' => (bool) $control->technical_failure,
            'abuse_flag' => (bool) $control->abuse_flag,
            'operational_resolution' => $control->operational_resolution,
        ];
    }
}
