<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Http\Controllers;

use App\Models\AdminSession;
use App\Models\RemoteConfigEntry;
use App\Modules\Admin\AnalyticsOperations\Exceptions\AnalyticsOperationsException;
use App\Modules\Admin\AnalyticsOperations\Services\OperationalSanitizer;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\Request;

final class RemoteConfigController
{
    public function index(Request $r)
    {
        return AdminApiResponse::success($r, ['items' => RemoteConfigEntry::query()->orderBy('key')->get()]);
    }

    public function upsert(Request $r, string $key, OperationalSanitizer $san, AdminAuditLogger $audit)
    {
        $san->rejectSecretKey($key);
        $d = $r->validate(['environment' => 'nullable|string|max:24', 'value' => 'required|array', 'description' => 'nullable|string|max:1000', 'status' => 'nullable|in:active,inactive', 'critical' => 'nullable|boolean', 'reason' => 'required|string|min:4|max:500']);
        $env = $d['environment'] ?? 'production';
        $existing = RemoteConfigEntry::query()->where('key', $key)->where('environment', $env)->first();
        $critical = (bool) ($d['critical'] ?? $existing?->critical ?? false);
        if ($critical) {
            if (! $r->user()->hasPermission('remote_config.critical.manage')) {
                throw new AnalyticsOperationsException('ADMIN_FORBIDDEN', 'Critical runtime configuration requires a separately assigned permission.', 403);
            }$session = $r->attributes->get('admin_session');
            $window = max(1, (int) config('orbit_admin.reauth_window_minutes', 10));
            if (! $session instanceof AdminSession || $session->reauthenticated_at === null || $session->reauthenticated_at->lt(now()->subMinutes($window))) {
                throw new AnalyticsOperationsException('ADMIN_REAUTH_REQUIRED', 'Recent administrator reauthentication is required for critical runtime configuration.', 428);
            }
        } $before = $existing?->toArray() ?? [];
        $entry = RemoteConfigEntry::query()->updateOrCreate(['key' => $key, 'environment' => $env], ['value' => $san->sanitize($d['value']), 'description' => $d['description'] ?? $existing?->description, 'status' => $d['status'] ?? $existing?->status ?? 'active', 'critical' => $critical, 'updated_by_admin_id' => $r->user()->id]);
        $audit->write('remote_config.updated', $r->user(), $r->attributes->get('admin_session'), 'remote_config', $entry->id, reason: $d['reason'], before: $before, after: $entry->toArray(), request: $r);

        return AdminApiResponse::success($r,$entry);
    }
}
