<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Services;

use App\Models\ModerationAppeal;
use App\Models\ModerationReport;

final class ModerationPresenter
{
    public function report(ModerationReport $report, bool $detail = false): array
    {
        $data = [
            'id' => (string) $report->id, 'source' => $report->source, 'reporter_user_id' => $report->reporter_user_id,
            'target_type' => $report->target_type, 'target_id' => $report->target_id, 'target_user_id' => $report->target_user_id,
            'reason' => $report->reason, 'status' => $report->status, 'priority' => $report->priority, 'risk_score' => (int) $report->risk_score,
            'assigned_admin_id' => $report->assigned_admin_id, 'created_at' => $report->created_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
        if ($detail) {
            $data += [
                'details' => $report->details,
                'evidence' => $report->evidence ?? ['origin' => 'none', 'text' => null, 'refs' => []],
                'target' => $report->target_snapshot ?? [],
                'workflow' => [
                    'triaged_at' => $report->triaged_at?->toIso8601String(),
                    'review_started_at' => $report->review_started_at?->toIso8601String(),
                    'actioned_at' => $report->actioned_at?->toIso8601String(),
                    'escalated_at' => $report->escalated_at?->toIso8601String(),
                    'closed_at' => $report->closed_at?->toIso8601String(),
                ],
                'notes' => $report->notes->map(fn ($n) => [
                    'id' => (string) $n->id, 'admin_user_id' => $n->admin_user_id, 'note' => $n->note, 'created_at' => $n->created_at?->toIso8601String(),
                ])->all(),
                'prior_reports' => ModerationReport::query()
                    ->where('target_type', $report->target_type)->where('target_id', $report->target_id)
                    ->whereKeyNot($report->id)->latest('created_at')->limit(10)->get()
                    ->map(fn (ModerationReport $prior) => [
                        'id' => (string) $prior->id, 'reason' => $prior->reason, 'status' => $prior->status,
                        'priority' => $prior->priority, 'created_at' => $prior->created_at?->toIso8601String(),
                    ])->all(),
                'enforcements' => $report->enforcements->map(fn ($e) => [
                    'id' => (string) $e->id, 'action' => $e->action, 'target_type' => $e->target_type, 'target_id' => $e->target_id,
                    'status' => $e->status, 'parameters' => $e->parameters ?? [], 'reason' => $e->reason,
                    'applied_at' => $e->applied_at?->toIso8601String(), 'reversed_at' => $e->reversed_at?->toIso8601String(),
                ])->all(),
            ];
        }

        return $data;
    }

    public function appeal(ModerationAppeal $appeal): array
    {
        return [
            'id' => (string) $appeal->id, 'enforcement_id' => (string) $appeal->enforcement_id, 'user_id' => (int) $appeal->user_id,
            'explanation' => $appeal->explanation, 'status' => $appeal->status, 'assigned_admin_id' => $appeal->assigned_admin_id,
            'outcome' => $appeal->outcome, 'decision_reason' => $appeal->decision_reason,
            'requires_second_review' => (bool) $appeal->requires_second_review,
            'reviewer_admin_id' => $appeal->reviewer_admin_id, 'second_reviewer_admin_id' => $appeal->second_reviewer_admin_id,
            'enforcement' => $appeal->enforcement ? [
                'id' => (string) $appeal->enforcement->id, 'action' => $appeal->enforcement->action,
                'target_type' => $appeal->enforcement->target_type, 'target_id' => $appeal->enforcement->target_id,
                'status' => $appeal->enforcement->status, 'applied_at' => $appeal->enforcement->applied_at?->toIso8601String(),
            ] : null,
            'submitted_at' => $appeal->submitted_at?->toIso8601String(), 'reviewed_at' => $appeal->reviewed_at?->toIso8601String(),
            'second_reviewed_at' => $appeal->second_reviewed_at?->toIso8601String(),
        ];
    }
}
