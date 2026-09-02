<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\ModerationCaseNote;
use App\Models\ModerationReport;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Admin\Services\AdminAuditLogger;
use Illuminate\Http\Request;

final readonly class ModerationWorkflowService
{
    public function __construct(private AdminAuditLogger $audit, private ModerationIntakeService $intake) {}

    public function assign(ModerationReport $report, ?AdminUser $assignee, AdminUser $admin, AdminSession $session, string $reason, Request $request): ModerationReport
    {
        if ($assignee && (! $assignee->isOperationallyActive() || ! $assignee->hasPermission('reports.review'))) {
            throw new ModerationDomainException('REPORT_ASSIGNEE_INVALID', 'The selected moderator cannot review reports.', 422);
        }
        $before = ['assigned_admin_id' => $report->assigned_admin_id, 'status' => $report->status];
        $report->assigned_admin_id = $assignee?->id;
        if ($assignee && in_array($report->status, ['new', 'triaged'], true)) {
            $report->status = 'assigned';
        }
        $report->save();
        $this->audit->write('admin.moderation.report.assigned', $admin, $session, 'moderation_report', $report->id, reason: $reason, before: $before, after: ['assigned_admin_id' => $report->assigned_admin_id, 'status' => $report->status], request: $request);
        $this->intake->broadcast($report);

        return $report->refresh();
    }

    public function transition(ModerationReport $report, string $status, ?string $priority, ?int $riskScore, AdminUser $admin, AdminSession $session, string $reason, Request $request): ModerationReport
    {
        $allowed = [
            'new' => ['triaged', 'assigned', 'escalated', 'closed'],
            'triaged' => ['assigned', 'under_review', 'escalated', 'closed'],
            'assigned' => ['under_review', 'escalated', 'closed'],
            'under_review' => ['actioned', 'escalated', 'closed'],
            'actioned' => ['escalated', 'closed'],
            'escalated' => ['under_review', 'actioned', 'closed'],
            'closed' => [],
        ];
        if ($status !== $report->status && ! in_array($status, $allowed[$report->status] ?? [], true)) {
            throw new ModerationDomainException('REPORT_TRANSITION_INVALID', 'That moderation workflow transition is not allowed.', 409);
        }
        $before = ['status' => $report->status, 'priority' => $report->priority, 'risk_score' => (int) $report->risk_score];
        if ($status !== $report->status) {
            $report->status = $status;
            $field = match ($status) {
                'triaged' => 'triaged_at','under_review' => 'review_started_at','actioned' => 'actioned_at',
                'escalated' => 'escalated_at','closed' => 'closed_at',default => null,
            };
            if ($field) {
                $report->{$field} ??= now();
            }
        }
        if ($priority !== null) {
            $report->priority = $priority;
        }
        if ($riskScore !== null) {
            $report->risk_score = $riskScore;
        }
        $report->save();
        $this->audit->write('admin.moderation.report.workflow.updated', $admin, $session, 'moderation_report', $report->id, reason: $reason, before: $before, after: ['status' => $report->status, 'priority' => $report->priority, 'risk_score' => (int) $report->risk_score], request: $request);
        $this->intake->broadcast($report);

        return $report->refresh();
    }

    public function addNote(ModerationReport $report, AdminUser $admin, AdminSession $session, string $note, Request $request): ModerationCaseNote
    {
        $row = ModerationCaseNote::query()->create(['report_id' => $report->id, 'admin_user_id' => $admin->id, 'note' => $note, 'created_at' => now()]);
        $this->audit->write('admin.moderation.report.note.created', $admin, $session, 'moderation_report', $report->id, after: ['note_id' => (string) $row->id], request: $request);

        return $row;
    }
}
