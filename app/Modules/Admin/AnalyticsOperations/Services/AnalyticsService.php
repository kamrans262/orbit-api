<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AnalyticsService
{
    public const array METRICS = [
        'users.registrations', 'users.dau', 'users.wau', 'users.mau',
        'circles.created', 'circles.memberships', 'messaging.messages', 'messaging.deliveries',
        'moments.created', 'moments.ready', 'sos.activations', 'sos.resolved', 'sos.false_alarms',
        'subscriptions.active', 'subscriptions.cancel_pending', 'payments.gross_minor', 'payments.refunds_minor',
        'notifications.deliveries', 'notifications.failures', 'media.uploads', 'media.failed_uploads',
    ];

    public function center(?string $from = null, ?string $to = null): array
    {
        $fromAt = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(29)->startOfDay();
        $toAt = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        return [
            'range' => ['from' => $fromAt->toIso8601String(), 'to' => $toAt->toIso8601String()],
            'users' => [
                'registrations' => $this->count('users', 'created_at', $fromAt, $toAt),
                'dau' => $this->activeUsers(now()->subDay()), 'wau' => $this->activeUsers(now()->subDays(7)), 'mau' => $this->activeUsers(now()->subDays(30)),
            ],
            'circles' => ['created' => $this->count('circles', 'created_at', $fromAt, $toAt), 'memberships' => $this->count('circle_members', 'joined_at', $fromAt, $toAt)],
            'messaging' => ['messages' => $this->count('messages', 'created_at', $fromAt, $toAt), 'deliveries' => $this->count('message_delivery_receipts', 'delivered_at', $fromAt, $toAt)],
            'moments' => ['created' => $this->count('moments', 'created_at', $fromAt, $toAt), 'ready' => $this->whereCount('moments', 'status', 'active', $fromAt, $toAt)],
            'sos' => ['activations' => $this->count('sos_events', 'activated_at', $fromAt, $toAt), 'resolved' => $this->notNullCount('sos_events', 'resolved_at', $fromAt, $toAt), 'false_alarms' => $this->whereCount('admin_sos_incident_controls', 'classification', 'false_alarm', $fromAt, $toAt, 'updated_at')],
            'subscriptions' => ['active' => $this->whereCountNoRange('user_subscriptions', 'status', 'active'), 'cancel_pending' => $this->whereCountNoRange('user_subscriptions', 'status', 'cancel_pending')],
            'payments' => ['gross_minor' => $this->sum('payment_transactions', 'amount_minor', 'occurred_at', $fromAt, $toAt, ['status' => 'succeeded']), 'refunds_minor' => $this->sum('payment_refunds', 'amount_minor', 'created_at', $fromAt, $toAt, ['status' => 'succeeded'])],
            'notifications' => ['deliveries' => $this->count('notification_deliveries', 'created_at', $fromAt, $toAt), 'failures' => $this->whereCount('notification_deliveries', 'status', 'failed', $fromAt, $toAt)],
            'media' => ['uploads' => $this->count('media_uploads', 'created_at', $fromAt, $toAt), 'failed_uploads' => $this->whereCount('media_uploads', 'status', 'failed', $fromAt, $toAt)],
        ];
    }

    public function run(array $metrics, array $filters = []): array
    {
        $from = $filters['from'] ?? now()->subDays(29)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $center = $this->center((string) $from, (string) $to);
        $flat = collect($center)->except('range')->dot();
        $rows = [];
        foreach ($metrics as $metric) {
            if (in_array($metric, self::METRICS, true)) {
                $rows[] = ['metric' => $metric, 'value' => (int) ($flat[$metric] ?? 0)];
            }
        }

        return ['range' => $center['range'], 'rows' => $rows];
    }

    private function count(string $table, string $column, $from, $to): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

return DB::table($table)->whereBetween($column, [$from, $to])->count();
    }

    private function whereCount(string $table, string $column, mixed $value, $from, $to, string $date = 'created_at'): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        } $q = DB::table($table)->where($column, $value);
        if (Schema::hasColumn($table, $date)) {
            $q->whereBetween($date, [$from, $to]);
        }

return $q->count();
    }

    private function whereCountNoRange(string $table, string $column, mixed $value): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column) ? DB::table($table)->where($column, $value)->count() : 0;
    }

    private function notNullCount(string $table, string $column, $from, $to): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

return DB::table($table)->whereNotNull($column)->whereBetween($column, [$from, $to])->count();
    }

    private function sum(string $table, string $amount, string $date, $from, $to, array $where = []): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $amount)) {
            return 0;
        } $q = DB::table($table);
        if (Schema::hasColumn($table, $date)) {
            $q->whereBetween($date, [$from, $to]);
        } foreach ($where as $k => $v) {
            if (Schema::hasColumn($table, $k)) {
                $q->where($k, $v);
            }
        }

return (int) $q->sum($amount);
    }

    private function activeUsers($since): int
    {
        if (Schema::hasTable('identity_sessions') && Schema::hasColumn('identity_sessions','last_seen_at')) {
            return DB::table('identity_sessions')->where('last_seen_at','>=',$since)->distinct()->count('user_id');
        } if (Schema::hasTable('devices') && Schema::hasColumn('devices','last_seen_at')) {
            return DB::table('devices')->where('last_seen_at','>=',$since)->distinct()->count('user_id');
        }

return 0;
    }
}
