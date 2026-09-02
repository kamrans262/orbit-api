<?php

declare(strict_types=1);

namespace App\Modules\Admin\BackendCompletion\Services;

use App\Modules\Admin\AnalyticsOperations\Services\AnalyticsService;
use App\Modules\Admin\AnalyticsOperations\Services\SystemHealthService;
use App\Modules\Admin\BillingAdvertising\Services\RevenueAnalyticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class AdminDashboardService
{
    public function __construct(
        private AnalyticsService $analytics,
        private RevenueAnalyticsService $revenue,
        private SystemHealthService $health,
    ) {}

    public function snapshot(): array
    {
        $analytics = $this->analytics->center(now()->subDays(29)->toDateString(), now()->toDateString());
        $revenue = $this->revenue->summary(now()->subDays(29)->toDateString(), now()->toDateString());

        return [
            'environment' => app()->environment(),
            'generated_at' => now()->toIso8601String(),
            'business' => [
                'users' => [
                    'total' => $this->countAll('users'),
                    'new_today' => $this->countSince('users', 'created_at', now()->startOfDay()),
                    'new_week' => $this->countSince('users', 'created_at', now()->subDays(6)->startOfDay()),
                    'new_month' => $this->countSince('users', 'created_at', now()->subDays(29)->startOfDay()),
                    'dau' => (int) data_get($analytics, 'users.dau', 0),
                    'wau' => (int) data_get($analytics, 'users.wau', 0),
                    'mau' => (int) data_get($analytics, 'users.mau', 0),
                    'online' => $this->onlineUsers(),
                    'active_devices' => $this->activeDevices(),
                ],
                'circles' => [
                    'active' => $this->activeCircles(),
                    'created_today' => $this->countSince('circles', 'created_at', now()->startOfDay()),
                ],
                'engagement_today' => [
                    'messages_routed' => $this->countSince('messages', 'created_at', now()->startOfDay()),
                    'moments_created' => $this->countSince('moments', 'created_at', now()->startOfDay()),
                    'pings_sent' => $this->countSince('pings', 'created_at', now()->startOfDay()),
                ],
                'safety' => [
                    'active_sos' => $this->whereCount('sos_events', 'status', 'active'),
                    'sos_today' => $this->countSince('sos_events', 'activated_at', now()->startOfDay()),
                ],
                'backlog' => [
                    'moderation' => $this->notInCount('moderation_reports', 'status', ['closed']),
                    'support' => $this->notInCount('support_tickets', 'status', ['resolved', 'closed']),
                ],
                'subscriptions' => $revenue,
            ],
            'operations' => $this->health->snapshot(),
        ];
    }

    private function countAll(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function countSince(string $table, string $column, mixed $since): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->where($column, '>=', $since)->count()
            : 0;
    }

    private function whereCount(string $table, string $column, mixed $value): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->where($column, $value)->count()
            : 0;
    }

    private function notInCount(string $table, string $column, array $values): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->whereNotIn($column, $values)->count()
            : 0;
    }

    private function onlineUsers(): int
    {
        if (! Schema::hasTable('presence_states') || ! Schema::hasColumn('presence_states', 'updated_at')) {
            return 0;
        }

        $seconds = max(1, (int) config('orbit.presence.offline_after_seconds', 120));

        return DB::table('presence_states')
            ->where('updated_at', '>=', now()->subSeconds($seconds))
            ->where('status', '!=', 'offline')
            ->distinct()
            ->count('user_id');
    }

    private function activeDevices(): int
    {
        if (! Schema::hasTable('devices') || ! Schema::hasColumn('devices', 'last_seen_at')) {
            return 0;
        }

        return DB::table('devices')
            ->whereNull('revoked_at')
            ->where('last_seen_at', '>=', now()->subDays(30))
            ->count();
    }

    private function activeCircles(): int
    {
        if (! Schema::hasTable('circles')) {
            return 0;
        }

        return DB::table('circles')
            ->whereNull('archived_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
    }
}
