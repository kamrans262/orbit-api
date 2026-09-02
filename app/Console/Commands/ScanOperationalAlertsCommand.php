<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminOperationalAlert;
use App\Models\IntegrationStatus;
use App\Modules\Admin\AnalyticsOperations\Events\AdminOperationalAlertRaised;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ScanOperationalAlertsCommand extends Command
{
    protected $signature = 'orbit:operations:scan-alerts';

    protected $description = 'Create role-visible operational alerts from real queue and integration state.';

    public function handle(): int
    {
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        if ($failed > 0) {
            $this->upsert('queue_failures', 'high', 'failed_jobs', 'global', 'Queue failures require attention', "{$failed} failed queue job(s) are currently recorded.", ['failed_count' => $failed]);
        }foreach (IntegrationStatus::query()->whereIn('health', ['degraded', 'down'])->get() as $i) {
            $this->upsert('integration_health', $i->health === 'down' ? 'critical' : 'high', 'integration', $i->id, "{$i->service} integration is {$i->health}", "Provider {$i->provider} is reporting {$i->health} health.", ['service' => $i->service, 'provider' => $i->provider]);
        }

return self::SUCCESS;
    }

    private function upsert($kind, $severity, $type, $id, $title, $message, $meta): void
    {
        $alert = AdminOperationalAlert::query()->firstOrCreate(['kind' => $kind, 'resource_type' => $type, 'resource_id' => (string) $id, 'status' => 'open'], ['severity' => $severity, 'title' => $title, 'message' => $message, 'metadata' => $meta]);
        if ($alert->wasRecentlyCreated) {
            AdminOperationalAlertRaised::dispatch(['alert_id' => $alert->id, 'kind' => $alert->kind, 'severity' => $alert->severity, 'resource_type' => $alert->resource_type, 'resource_id' => $alert->resource_id, 'title' => $alert->title, 'created_at' => $alert->created_at?->toIso8601String()]);
        }
    }
}
