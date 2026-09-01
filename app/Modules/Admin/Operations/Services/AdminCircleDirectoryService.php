<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Services;

use App\Models\Circle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final readonly class AdminCircleDirectoryService
{
    public function __construct(private AdminCircleOperationsPresenter $presenter) {}

    /** @param array<string,mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Circle::query()->select('circles.*')
            ->with([
                'adminOperationalControl',
                'memberships' => fn ($memberships) => $memberships->where('role', 'owner')->with('user'),
            ])
            ->withCount(['memberships as admin_member_count'])
            ->addSelect([
                'admin_activity_count' => DB::table('activity_events')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('activity_events.circle_id', 'circles.id'),
                'admin_sos_count' => DB::table('sos_events')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('sos_events.circle_id', 'circles.id'),
                'admin_active_sos_count' => DB::table('sos_events')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('sos_events.circle_id', 'circles.id')
                    ->where('sos_events.status', 'active'),
                'admin_report_count' => DB::table('activity_reports')
                    ->join('activity_events', 'activity_events.id', '=', 'activity_reports.activity_event_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('activity_events.circle_id', 'circles.id'),
                'admin_safety_tag_count' => DB::table('admin_record_tags')
                    ->selectRaw('COUNT(*)')
                    ->where('admin_record_tags.target_type', 'circle')
                    ->whereColumn('admin_record_tags.target_id', 'circles.id')
                    ->whereIn('admin_record_tags.tag', ['Safety Concern', 'Abuse Watch', 'Legal Hold']),
            ]);
        if (($filters['search'] ?? null) !== null) {
            $search = trim((string) $filters['search']);
            $query->where(fn ($builder) => $builder->where('name', 'like', '%'.$search.'%')->orWhere('id', $search));
        }
        if (($filters['owner_user_id'] ?? null) !== null) {
            $ownerId = (int) $filters['owner_user_id'];
            $query->whereHas('memberships', fn ($membershipQuery) => $membershipQuery->where('user_id', $ownerId)->where('role', 'owner'));
        }
        if (($filters['status'] ?? null) === 'archived') {
            $query->whereNotNull('archived_at');
        } elseif (($filters['status'] ?? null) === 'active') {
            $query->whereNull('archived_at')->whereDoesntHave('adminOperationalControl', fn ($controlQuery) => $controlQuery->whereIn('status', ['frozen', 'removed']));
        } elseif (in_array(($filters['status'] ?? null), ['frozen', 'removed'], true)) {
            $query->whereHas('adminOperationalControl', fn ($controlQuery) => $controlQuery->where('status', $filters['status']));
        }
        if (isset($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (isset($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        if (isset($filters['expires_from'])) {
            $query->where('expires_at', '>=', $filters['expires_from']);
        }
        if (isset($filters['expires_to'])) {
            $query->where('expires_at', '<=', $filters['expires_to']);
        }
        if (($filters['has_sos'] ?? null) !== null) {
            $hasSos = (bool) $filters['has_sos'];
            $hasSos
                ? $query->whereExists(fn ($sos) => $sos->selectRaw('1')->from('sos_events')->whereColumn('sos_events.circle_id', 'circles.id'))
                : $query->whereNotExists(fn ($sos) => $sos->selectRaw('1')->from('sos_events')->whereColumn('sos_events.circle_id', 'circles.id'));
        }
        if (isset($filters['min_member_count'])) {
            $query->has('memberships', '>=', (int) $filters['min_member_count']);
        }
        if (isset($filters['max_member_count'])) {
            $query->has('memberships', '<=', (int) $filters['max_member_count']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), (int) config('orbit_admin_operations.directory_max_per_page', 100));
        $paginator = $query->latest('created_at')->paginate($perPage);
        $paginator->setCollection($paginator->getCollection()->map(fn (Circle $circle): array => $this->presenter->summary($circle)));

        return $paginator;
    }
}
