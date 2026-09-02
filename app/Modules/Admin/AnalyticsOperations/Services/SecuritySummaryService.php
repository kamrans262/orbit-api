<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SecuritySummaryService
{
    public function summary(): array
    {
        return [
            'admin_failed_logins' => $this->booleanCount('admin_login_events', 'success', false),
            'admin_locked_accounts' => $this->notNull('admin_users', 'locked_until'),
            'refresh_reuse_events' => $this->notNull('identity_refresh_tokens', 'reuse_detected_at'),
            'open_risk_signals' => $this->nullCount('admin_risk_signals', 'resolved_at'),
            'suspended_users' => $this->whereCount('admin_user_controls', 'status', 'suspended'),
        ];
    }

    private function booleanCount(string $table, string $column, bool $value): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->where($column, $value)->count()
            : 0;
    }

    private function whereCount(string $table, string $column, string $value): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->where($column, $value)->count()
            : 0;
    }

    private function notNull(string $table, string $column): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->whereNotNull($column)->count()
            : 0;
    }

    private function nullCount(string $table, string $column): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->whereNull($column)->count()
            : 0;
    }
}
