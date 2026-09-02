<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Services;

use App\Models\ActivityEvent;
use App\Models\ActivityReport;
use App\Models\ModerationReport;
use App\Models\User;
use App\Modules\Admin\Moderation\Broadcasts\AdminModerationRealtimeBroadcast;
use Illuminate\Support\Facades\DB;

final readonly class ModerationIntakeService
{
    public function __construct(
        private ModerationReportAccessService $access,
        private AdminRiskService $risk,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(User $reporter, array $data): ModerationReport
    {
        $clientId = isset($data['client_report_id']) ? (string) $data['client_report_id'] : null;
        if ($clientId) {
            $existing = ModerationReport::query()->where('client_report_id', $clientId)->where('reporter_user_id', $reporter->id)->first();
            if ($existing) {
                return $existing;
            }
        }

        $resolved = $this->access->resolve($reporter, (string) $data['target_type'], (string) $data['target_id']);
        [$priority,$score,$severity] = $this->classification((string) $data['reason']);

        $report = DB::transaction(function () use ($reporter, $data, $clientId, $resolved, $priority, $score, $severity) {
            $evidence = [
                'origin' => 'reporter_submitted',
                'text' => $data['evidence_text'] ?? null,
                'refs' => array_values($data['evidence_refs'] ?? []),
            ];
            $report = ModerationReport::query()->create([
                'client_report_id' => $clientId,
                'reporter_user_id' => $reporter->id,
                'target_type' => $data['target_type'],
                'target_id' => (string) $data['target_id'],
                'target_user_id' => $resolved['target_user_id'],
                'source' => 'consumer',
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'evidence' => $evidence,
                'target_snapshot' => $resolved['snapshot'],
                'status' => 'new', 'priority' => $priority, 'risk_score' => $score,
            ]);
            if ($resolved['target_user_id']) {
                $type = $data['reason'] === 'sos_misuse' ? 'sos_misuse' : 'report_received';
                $this->risk->record((int) $resolved['target_user_id'], $type, $severity, 'moderation_report', (string) $report->id, [
                    'reason' => $data['reason'], 'target_type' => $data['target_type'],
                ]);
            }

            return $report;
        });

        $this->broadcast($report);

        return $report;
    }

    public function ingestActivityReport(ActivityReport $activity): ModerationReport
    {
        $existing = ModerationReport::query()->where('source', 'activity')->where('source_report_id', $activity->id)->first();
        if ($existing) {
            return $existing;
        }

        $event = $activity->activity_event_id
            ? ActivityEvent::query()->find($activity->activity_event_id)
            : null;
        [$priority,$score,$severity] = $this->classification((string) $activity->reason);
        $report = ModerationReport::query()->create([
            'reporter_user_id' => $activity->user_id,
            'target_type' => 'activity', 'target_id' => (string) $activity->activity_event_id,
            'target_user_id' => $event?->actor_user_id,
            'source' => 'activity', 'source_report_id' => (string) $activity->id,
            'reason' => (string) $activity->reason, 'details' => $activity->details,
            'evidence' => ['origin' => 'activity_report', 'text' => null, 'refs' => []],
            'target_snapshot' => $event ? [
                'type' => 'activity', 'id' => (string) $event->id, 'circle_id' => (string) $event->circle_id,
                'actor_user_id' => $event->actor_user_id, 'event_type' => (string) $event->event_type,
                'source_type' => (string) $event->source_type, 'source_id' => $event->source_id,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'privacy' => 'safe_activity_metadata_only',
            ] : ['type' => 'activity', 'id' => (string) $activity->activity_event_id, 'unavailable' => true],
            'status' => 'new', 'priority' => $priority, 'risk_score' => $score,
        ]);
        if ($event?->actor_user_id) {
            $this->risk->record((int) $event->actor_user_id, 'report_received', $severity, 'moderation_report', (string) $report->id, ['reason' => $activity->reason]);
        }
        $this->broadcast($report);

        return $report;
    }

    public function importExistingActivityReports(): int
    {
        $count = 0;
        ActivityReport::query()->orderBy('id')->chunk(100, function ($rows) use (&$count): void {
            foreach ($rows as $row) {
                $before = ModerationReport::query()->where('source', 'activity')->where('source_report_id', $row->id)->exists();
                $this->ingestActivityReport($row);
                if (! $before) {
                    $count++;
                }
            }
        });

        return $count;
    }

    public function broadcast(ModerationReport $report): void
    {
        AdminModerationRealtimeBroadcast::dispatch([
            'report_id' => (string) $report->id, 'status' => $report->status, 'priority' => $report->priority,
            'risk_score' => (int) $report->risk_score, 'target_type' => $report->target_type, 'target_id' => $report->target_id,
            'assigned_admin_id' => $report->assigned_admin_id, 'created_at' => $report->created_at?->toIso8601String(),
        ]);
    }

    /** @return array{0:string,1:int,2:string} */
    private function classification(string $reason): array
    {
        return match ($reason) {
            'threats','safety','sos_misuse' => ['high', 70, 'high'],
            'harassment','abuse' => ['normal', 45, 'medium'],
            'spam','fake_account' => ['normal', 25, 'medium'],
            default => ['low', 10, 'low'],
        };
    }
}
