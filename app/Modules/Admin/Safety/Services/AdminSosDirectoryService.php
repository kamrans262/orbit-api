<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Services;

use App\Models\SosEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class AdminSosDirectoryService
{
    public function __construct(private AdminSosPresenter $presenter) {}

    /** @param array<string,mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = SosEvent::query()
            ->with([
                'originator:id,name,email',
                'circle:id,name',
                'adminSafetyControl.assignedAdmin:id,name',
            ]);

        if (($filters['search'] ?? null) !== null) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('id', $search)
                    ->orWhereHas('originator', fn (Builder $user) => $user
                        ->where('email', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('circle', fn (Builder $circle) => $circle
                        ->where('name', 'like', '%'.$search.'%'));
            });
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', $filters['status']);
        }
        if (($filters['user_id'] ?? null) !== null) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (($filters['circle_id'] ?? null) !== null) {
            $query->where('circle_id', (string) $filters['circle_id']);
        }
        if (($filters['escalation_min'] ?? null) !== null) {
            $query->where('escalation_stage', '>=', (int) $filters['escalation_min']);
        }
        if (($filters['escalation_max'] ?? null) !== null) {
            $query->where('escalation_stage', '<=', (int) $filters['escalation_max']);
        }
        if (($filters['activated_from'] ?? null) !== null) {
            $query->where('activated_at', '>=', $filters['activated_from']);
        }
        if (($filters['activated_to'] ?? null) !== null) {
            $query->where('activated_at', '<=', $filters['activated_to']);
        }
        if (($filters['assigned_admin_id'] ?? null) !== null) {
            $query->whereHas('adminSafetyControl', fn (Builder $control) => $control
                ->where('assigned_admin_id', (int) $filters['assigned_admin_id']));
        }
        if (($filters['unassigned'] ?? null) !== null && (bool) $filters['unassigned']) {
            $query->whereDoesntHave('adminSafetyControl', fn (Builder $control) => $control
                ->whereNotNull('assigned_admin_id'));
        }
        if (($filters['operational_status'] ?? null) !== null) {
            $status = (string) $filters['operational_status'];
            if ($status === 'open') {
                $query->where(function (Builder $builder): void {
                    $builder->whereDoesntHave('adminSafetyControl')
                        ->orWhereHas('adminSafetyControl', fn (Builder $control) => $control->where('operational_status', 'open'));
                });
            } else {
                $query->whereHas('adminSafetyControl', fn (Builder $control) => $control->where('operational_status', $status));
            }
        }
        foreach (['false_alarm', 'technical_failure', 'abuse_flag'] as $flag) {
            if (($filters[$flag] ?? null) !== null) {
                $expected = (bool) $filters[$flag];
                $expected
                    ? $query->whereHas('adminSafetyControl', fn (Builder $control) => $control->where($flag, true))
                    : $query->where(function (Builder $builder) use ($flag): void {
                        $builder->whereDoesntHave('adminSafetyControl')
                            ->orWhereHas('adminSafetyControl', fn (Builder $control) => $control->where($flag, false));
                    });
            }
        }
        if (($filters['fallback_used'] ?? null) !== null) {
            $fallbackUsed = (bool) $filters['fallback_used'];
            $fallbackUsed
                ? $query->whereHas('escalations', fn (Builder $escalation) => $escalation->where('stage', '>=', 2))
                : $query->whereDoesntHave('escalations', fn (Builder $escalation) => $escalation->where('stage', '>=', 2));
        }
        if (($filters['delivery_failures'] ?? null) !== null && (bool) $filters['delivery_failures']) {
            $query->whereExists(fn ($outbox) => $outbox
                ->selectRaw('1')
                ->from('sos_notification_outbox')
                ->whereColumn('sos_notification_outbox.sos_event_id', 'sos_events.id')
                ->where(function ($failure): void {
                    $failure->where('attempts', '>', 1)
                        ->orWhereNotIn('status', ['pending', 'accepted']);
                }));
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $page = $query->latest('activated_at')->paginate($perPage);
        $page->setCollection($page->getCollection()->map(
            fn (SosEvent $incident): array => $this->presenter->summary($incident),
        ));

        return $page;
    }
}
