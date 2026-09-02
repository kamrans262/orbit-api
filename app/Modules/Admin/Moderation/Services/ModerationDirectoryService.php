<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Services;

use App\Models\AdminRiskProfile;
use App\Models\AdminRiskSignal;
use App\Models\ModerationAppeal;
use App\Models\ModerationReport;
use App\Models\User;

final readonly class ModerationDirectoryService
{
    public function __construct(private ModerationPresenter $presenter) {}

    public function reports(array $filters): array
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));
        $q = ModerationReport::query();
        foreach (['status', 'priority', 'target_type', 'reason'] as $key) {
            if (! empty($filters[$key])) {
                $q->where($key, $filters[$key]);
            }
        }
        if (! empty($filters['assigned_admin_id'])) {
            $q->where('assigned_admin_id', (int) $filters['assigned_admin_id']);
        }
        if (($filters['unassigned'] ?? null) === '1') {
            $q->whereNull('assigned_admin_id');
        }
        if (! empty($filters['target_user_id'])) {
            $q->where('target_user_id', (int) $filters['target_user_id']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
            $q->where(fn ($x) => $x->where('id', 'like', $term)->orWhere('target_id', 'like', $term)->orWhere('details', 'like', $term));
        }
        $page = $q->latest('created_at')->latest('id')->paginate($perPage);

        return ['data' => $page->getCollection()->map(fn ($r) => $this->presenter->report($r))->all(), 'meta' => [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]];
    }

    public function report(string $id): ModerationReport
    {
        return ModerationReport::query()->with(['notes', 'enforcements'])->findOrFail($id);
    }

    public function appeals(array $filters): array
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));
        $q = ModerationAppeal::query();
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (! empty($filters['user_id'])) {
            $q->where('user_id', (int) $filters['user_id']);
        }
        $page = $q->latest('submitted_at')->paginate($perPage);

        return ['data' => $page->getCollection()->map(fn ($a) => $this->presenter->appeal($a))->all(), 'meta' => [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]];
    }

    public function riskProfiles(array $filters): array
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));
        $q = AdminRiskProfile::query();
        if (! empty($filters['level'])) {
            $q->where('level', $filters['level']);
        }
        if (isset($filters['min_score'])) {
            $q->where('score', '>=', (int) $filters['min_score']);
        }
        $page = $q->orderByDesc('score')->paginate($perPage);

        return ['data' => $page->getCollection()->map(fn ($p) => [
            'user_id' => (int) $p->user_id, 'score' => (int) $p->score, 'level' => $p->level,
            'triggered_rules' => $p->triggered_rules ?? [], 'last_evaluated_at' => $p->last_evaluated_at?->toIso8601String(),
        ])->all(), 'meta' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]];
    }

    public function riskUser(User $user): array
    {
        $profile = AdminRiskProfile::query()->firstOrCreate(['user_id' => $user->id], ['score' => 0, 'level' => 'normal', 'triggered_rules' => []]);

        return [
            'user' => ['id' => (int) $user->id, 'name' => $user->name],
            'profile' => ['score' => (int) $profile->score, 'level' => $profile->level, 'triggered_rules' => $profile->triggered_rules ?? [], 'analyst_notes' => $profile->analyst_notes],
            'signals' => AdminRiskSignal::query()->where('user_id', $user->id)->latest('occurred_at')->limit(100)->get()->map(fn ($s) => [
                'id' => (string) $s->id, 'type' => $s->type, 'severity' => $s->severity, 'source' => $s->source, 'source_id' => $s->source_id,
                'metadata' => $s->metadata ?? [], 'occurred_at' => $s->occurred_at?->toIso8601String(), 'resolved_at' => $s->resolved_at?->toIso8601String(),
            ])->all(),
            'recent_reports' => ModerationReport::query()->where('target_user_id', $user->id)->latest()->limit(20)->get()->map(fn ($r) => $this->presenter->report($r))->all(),
        ];
    }
}
