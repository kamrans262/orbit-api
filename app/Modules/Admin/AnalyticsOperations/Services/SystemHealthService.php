<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Services;

use App\Models\ApiRequestMetric;
use App\Models\IntegrationStatus;
use App\Models\WebsocketMetricSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SystemHealthService
{
    public function snapshot(): array
    {
        $db = 'healthy';
        try {
            DB::select('select 1');
        } catch (Throwable) {
            $db = 'down';
        }
        $since = now()->subMinutes(5);
        $api = ['requests' => 0, 'errors' => 0, 'p95_latency_ms' => 0];
        if (Schema::hasTable('api_request_metrics')) {
            $q = ApiRequestMetric::query()->where('occurred_at', '>=', $since);
            $api['requests'] = (clone $q)->count();
            $api['errors'] = (clone $q)->where('status_code', '>=', 500)->count();
            $lat = (clone $q)->orderBy('latency_ms')->pluck('latency_ms')->all();
            if ($lat) {
                $api['p95_latency_ms'] = (int) $lat[min(count($lat) - 1, (int) floor(count($lat) * .95))];
            }
        }
        $queue = ['pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0, 'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0];
        $notify = ['total' => 0, 'failed' => 0];
        if (Schema::hasTable('notification_deliveries')) {
            $notify['total'] = DB::table('notification_deliveries')->count();
            if (Schema::hasColumn('notification_deliveries', 'status')) {
                $notify['failed'] = DB::table('notification_deliveries')->where('status', 'failed')->count();
            }
        }
        $media = ['uploads' => Schema::hasTable('media_uploads') ? DB::table('media_uploads')->count() : 0, 'failed' => 0];
        if (Schema::hasTable('media_uploads') && Schema::hasColumn('media_uploads', 'status')) {
            $media['failed'] = DB::table('media_uploads')->where('status', 'failed')->count();
        }
        $ws = WebsocketMetricSnapshot::query()->latest('captured_at')->first();

        return ['database' => ['status' => $db, 'driver' => DB::connection()->getDriverName()], 'api' => $api, 'queues' => $queue, 'notifications' => $notify, 'media' => $media, 'websocket' => $ws ? ['connections' => $ws->connections, 'subscriptions' => $ws->subscriptions, 'fanout_lag_ms' => $ws->fanout_lag_ms, 'captured_at' => $ws->captured_at?->toIso8601String()] : ['status' => 'no_telemetry'], 'integrations' => IntegrationStatus::query()->orderBy('service')->get()->map(fn ($i) => ['service' => $i->service, 'provider' => $i->provider, 'enabled' => $i->enabled, 'health' => $i->health, 'last_success_at' => $i->last_success_at?->toIso8601String(), 'last_failure_at' => $i->last_failure_at?->toIso8601String()])->all()];
    }

    public function queues(): array
    {
        $rows = [];
        if (Schema::hasTable('jobs')) {
            foreach (DB::table('jobs')->select('queue', DB::raw('COUNT(*) as depth'), DB::raw('MIN(available_at) as oldest'))->groupBy('queue')->get() as $r) {
                $rows[] = ['queue' => $r->queue, 'depth' => (int) $r->depth, 'oldest_available_at' => $r->oldest ? date(DATE_ATOM, (int) $r->oldest) : null];
            }
        }

return ['queues' => $rows, 'failed_count' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0];
    }

    public function failedJobs(int $limit = 50): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

return DB::table('failed_jobs')->orderByDesc('failed_at')->limit(min(100, $limit))->get()->map(fn ($j) => ['uuid' => (string) ($j->uuid ?? $j->id), 'connection' => (string) ($j->connection ?? ''), 'queue' => (string) ($j->queue ?? ''), 'failed_at' => (string) ($j->failed_at ?? ''), 'exception_summary' => mb_substr((string) ($j->exception ?? ''), 0, 240)])->all();
    }
}
