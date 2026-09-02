<?php

declare(strict_types=1);

namespace App\Modules\Admin\BackendCompletion\Services;

use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ReleaseReadinessService
{
    public function audit(): array
    {
        $checks = [];
        $production = app()->environment('production');

        $checks[] = $this->check('app_key', filled(config('app.key')), 'blocking', 'Application encryption key is configured.');
        $checks[] = $this->check('production_debug_disabled', ! $production || ! (bool) config('app.debug'), 'blocking', 'APP_DEBUG must be false in production.');
        $checks[] = $this->check('database_connectivity', $this->databaseHealthy(), 'blocking', 'Database connection responds to a health query.');
        $checks[] = $this->check('critical_tables', $this->criticalTablesPresent(), 'blocking', 'Critical Orbit and admin tables are present.');
        $checks[] = $this->check('admin_role_catalog', $this->roleCatalogHealthy(), 'blocking', 'Default administrator role catalog is synchronized.');
        $checks[] = $this->check('admin_mfa', $this->adminMfaHealthy(), 'blocking', 'Every active administrator has confirmed MFA.');
        $checks[] = $this->check('sensitive_permission_separation', $this->sensitivePermissionsSeparated(), 'blocking', 'Super Administrator does not silently inherit separately controlled high-risk permissions.');
        $checks[] = $this->check('queue_driver', ! $production || ! in_array((string) config('queue.default'), ['sync', 'null'], true), 'warning', 'Production should use a durable asynchronous queue driver.');
        $checks[] = $this->check('cache_driver', ! $production || ! in_array((string) config('cache.default'), ['array', 'null'], true), 'warning', 'Production should use a shared durable cache driver.');
        $checks[] = $this->check('reverb_origins', ! $production || $this->reverbOriginsRestricted(), 'blocking', 'Production Reverb allowed origins must be explicit and must not include wildcard origins.');
        $checks[] = $this->check('reverb_rate_limiting', ! $production || (bool) config('reverb.apps.apps.0.rate_limiting.enabled', false), 'blocking', 'Production Reverb connection rate limiting must be enabled.');
        $checks[] = $this->check('integrations_declared', $this->integrationCatalogPresent(), 'warning', 'Operational integration catalog is synchronized; provider-unconfigured states remain explicit.');

        $blockingFailures = collect($checks)->where('severity', 'blocking')->where('status', 'fail')->count();
        $warnings = collect($checks)->where('severity', 'warning')->where('status', 'fail')->count();

        return [
            'environment' => app()->environment(),
            'generated_at' => now()->toIso8601String(),
            'ready' => $blockingFailures === 0,
            'blocking_failures' => $blockingFailures,
            'warnings' => $warnings,
            'checks' => $checks,
            'deployment_dependencies' => $this->deploymentDependencies(),
        ];
    }

    private function check(string $key, bool $pass, string $severity, string $message): array
    {
        return ['key' => $key, 'status' => $pass ? 'pass' : 'fail', 'severity' => $severity, 'message' => $message];
    }

    private function databaseHealthy(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function criticalTablesPresent(): bool
    {
        try {
            foreach (['users', 'devices', 'circles', 'circle_members', 'sos_events', 'admin_users', 'admin_roles', 'admin_audit_logs'] as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function roleCatalogHealthy(): bool
    {
        try {
            return Schema::hasTable('admin_roles')
                && AdminRole::query()->where('is_system', true)->count() >= 14;
        } catch (Throwable) {
            return false;
        }
    }

    private function adminMfaHealthy(): bool
    {
        try {
            return Schema::hasTable('admin_users')
                && AdminUser::query()->where('status', 'active')->whereNull('mfa_confirmed_at')->doesntExist();
        } catch (Throwable) {
            return false;
        }
    }

    private function sensitivePermissionsSeparated(): bool
    {
        try {
            if (! Schema::hasTable('admin_roles') || ! Schema::hasTable('admin_permissions')) {
                return false;
            }
            $super = AdminRole::query()->where('slug', 'super-administrator')->first();
            if (! $super) {
                return false;
            }
            $separate = [
                'sensitive_fields.reveal',
                'sos.location.access',
                'sos.recordings.access',
                'sos.sensitive.audit',
                'privacy.identity.verify',
                'privacy.exports.manage',
                'privacy.exports.deliver',
                'privacy.deletions.manage',
                'refunds.approve',
                'communications.emergency.send',
                'legal.manage',
                'regions.manage',
                'app_versions.manage',
                'maintenance.manage',
                'feature_flags.modify',
                'remote_config.critical.manage',
                'operations.manage',
                'operations.telemetry.ingest',
                'queues.manage',
                'integrations.manage',
                'webhooks.retry',
                'security.ip_policies.manage',
            ];

            return ! $super->permissions()->whereIn('slug', $separate)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function reverbOriginsRestricted(): bool
    {
        $origins = config('reverb.apps.apps.0.allowed_origins', []);

        return is_array($origins) && $origins !== [] && ! in_array('*', $origins, true);
    }

    private function integrationCatalogPresent(): bool
    {
        try {
            return Schema::hasTable('integration_statuses') && DB::table('integration_statuses')->count() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function deploymentDependencies(): array
    {
        try {
            if (! Schema::hasTable('integration_statuses')) {
                return [];
            }

            return DB::table('integration_statuses')
                ->where(function ($query): void {
                    $query->where('enabled', false)->orWhereIn('health', ['unknown', 'unconfigured', 'degraded', 'down']);
                })
                ->orderBy('service')
                ->get(['service', 'provider', 'enabled', 'health'])
                ->map(fn ($row): array => [
                    'service' => (string) $row->service,
                    'provider' => (string) $row->provider,
                    'enabled' => (bool) $row->enabled,
                    'health' => (string) $row->health,
                ])->all();
        } catch (Throwable) {
            return [];
        }
    }
}
