<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final readonly class AdminUserDirectoryService
{
    public function __construct(private AdminUserOperationsPresenter $presenter) {}

    /** @param array<string,mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()->select('users.*')
            ->with('adminOperationalControl')
            ->withCount([
                'circleMemberships as admin_circle_count',
                'devices as admin_device_count',
                'devices as admin_active_device_count' => fn ($devices) => $devices->whereNull('revoked_at'),
            ])
            ->addSelect([
                'admin_sos_count' => DB::table('sos_events')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('sos_events.user_id', 'users.id'),
                'admin_last_device_seen_at' => DB::table('devices')
                    ->selectRaw('MAX(last_seen_at)')
                    ->whereColumn('devices.user_id', 'users.id'),
                'admin_last_session_seen_at' => DB::table('identity_sessions')
                    ->selectRaw('MAX(last_seen_at)')
                    ->whereColumn('identity_sessions.user_id', 'users.id'),
            ]);

        if (($filters['search'] ?? null) !== null) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('email', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $builder->orWhereKey((int) $search);
                }
            });
        }

        if (($filters['platform'] ?? null) !== null) {
            $query->whereHas('devices', fn ($deviceQuery) => $deviceQuery->where('platform', $filters['platform']));
        }

        if (($filters['risk_level'] ?? null) !== null) {
            $query->whereHas('adminOperationalControl', fn ($controlQuery) => $controlQuery->where('risk_level', $filters['risk_level']));
        }

        if (($filters['account_status'] ?? null) === 'deleted') {
            $query->whereNotNull('account_deleted_at');
        } elseif (($filters['account_status'] ?? null) === 'active') {
            $query->whereNull('account_deleted_at')->whereDoesntHave('adminOperationalControl', function ($controlQuery): void {
                $controlQuery->where('status', 'suspended')->where(function ($expiry): void {
                    $expiry->whereNull('suspended_until')->orWhere('suspended_until', '>', now());
                });
            });
        } elseif (($filters['account_status'] ?? null) === 'suspended') {
            $query->whereNull('account_deleted_at')->whereHas('adminOperationalControl', function ($controlQuery): void {
                $controlQuery->where('status', 'suspended')->where(function ($expiry): void {
                    $expiry->whereNull('suspended_until')->orWhere('suspended_until', '>', now());
                });
            });
        }

        if (isset($filters['registered_from'])) {
            $query->where('created_at', '>=', $filters['registered_from']);
        }
        if (isset($filters['registered_to'])) {
            $query->where('created_at', '<=', $filters['registered_to']);
        }
        if (($filters['verified'] ?? null) !== null) {
            $filters['verified'] ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at');
        }

        if (isset($filters['last_active_from']) || isset($filters['last_active_to'])) {
            $from = $filters['last_active_from'] ?? null;
            $to = $filters['last_active_to'] ?? null;
            $query->where(function ($activity) use ($from, $to): void {
                $activity->whereHas('devices', function ($devices) use ($from, $to): void {
                    $devices->whereNotNull('last_seen_at');
                    if ($from !== null) {
                        $devices->where('last_seen_at', '>=', $from);
                    }
                    if ($to !== null) {
                        $devices->where('last_seen_at', '<=', $to);
                    }
                })->orWhereExists(function ($sessions) use ($from, $to): void {
                    $sessions->selectRaw('1')->from('identity_sessions')
                        ->whereColumn('identity_sessions.user_id', 'users.id')
                        ->whereNotNull('identity_sessions.last_seen_at');
                    if ($from !== null) {
                        $sessions->where('identity_sessions.last_seen_at', '>=', $from);
                    }
                    if ($to !== null) {
                        $sessions->where('identity_sessions.last_seen_at', '<=', $to);
                    }
                });
            });
        }

        if (($filters['deletion_state'] ?? null) === 'completed') {
            $query->whereNotNull('account_deleted_at');
        } elseif (($filters['deletion_state'] ?? null) === 'scheduled') {
            $query->whereNull('account_deleted_at')->whereNotNull('account_deletion_scheduled_for');
        } elseif (($filters['deletion_state'] ?? null) === 'none') {
            $query->whereNull('account_deleted_at')->whereNull('account_deletion_scheduled_for');
        }

        if (($filters['has_sos'] ?? null) !== null) {
            $hasSos = (bool) $filters['has_sos'];
            $hasSos
                ? $query->whereExists(fn ($sos) => $sos->selectRaw('1')->from('sos_events')->whereColumn('sos_events.user_id', 'users.id'))
                : $query->whereNotExists(fn ($sos) => $sos->selectRaw('1')->from('sos_events')->whereColumn('sos_events.user_id', 'users.id'));
        }

        if (isset($filters['min_circle_count'])) {
            $query->has('circleMemberships', '>=', (int) $filters['min_circle_count']);
        }
        if (isset($filters['max_circle_count'])) {
            $query->has('circleMemberships', '<=', (int) $filters['max_circle_count']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), (int) config('orbit_admin_operations.directory_max_per_page', 100));
        $paginator = $query->latest('id')->paginate($perPage);
        $paginator->setCollection($paginator->getCollection()->map(fn (User $user): array => $this->presenter->summary($user)));

        return $paginator;
    }
}
