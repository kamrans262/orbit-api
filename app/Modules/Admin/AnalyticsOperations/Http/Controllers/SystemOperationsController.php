<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Http\Controllers;

use App\Models\AdminOperationalAlert;
use App\Models\AdminQueueAction;
use App\Models\AdminUser;
use App\Models\IntegrationStatus;
use App\Models\SystemIncident;
use App\Models\SystemIncidentNote;
use App\Models\WebhookDelivery;
use App\Models\WebsocketMetricSnapshot;
use App\Modules\Admin\AnalyticsOperations\Services\OperationalSanitizer;
use App\Modules\Admin\AnalyticsOperations\Services\SecuritySummaryService;
use App\Modules\Admin\AnalyticsOperations\Services\SystemHealthService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SystemOperationsController
{
    public function health(Request $r, SystemHealthService $s)
    {
        return AdminApiResponse::success($r, $s->snapshot());
    }

    public function queues(Request $r, SystemHealthService $s)
    {
        return AdminApiResponse::success($r, [...$s->queues(), 'failed_jobs' => $s->failedJobs((int) $r->integer('limit', 50))]);
    }

    public function queueAction(Request $r, string $uuid, AdminAuditLogger $audit)
    {
        if (! Schema::hasTable('failed_jobs') || ! DB::table('failed_jobs')->where('uuid', $uuid)->exists()) {
            abort(404);
        }$d = $r->validate(['action' => 'required|in:retry,quarantine', 'reason' => 'required|string|min:4|max:500']);
        $a = AdminQueueAction::query()->create(['failed_job_uuid' => $uuid, 'action' => $d['action'], 'reason' => $d['reason'], 'admin_user_id' => $r->user()->id]);
        $audit->write('queue.action.requested', $r->user(), $r->attributes->get('admin_session'), 'failed_job', $uuid, reason: $d['reason'], metadata: ['action' => $d['action']], request: $r);

        return AdminApiResponse::success($r, $a, 202);
    }

    public function incidents(Request $r)
    {
        $q = SystemIncident::query()->latest('started_at');
        foreach (['status', 'severity', 'service'] as $k) {
            if ($r->filled($k)) {
                $q->where($k, $r->string($k));
            }
        }

return AdminApiResponse::success($r, ['items' => $q->paginate(min(100, max(1, (int) $r->integer('per_page', 25))))->items()]);
    }

    public function showIncident(Request $r, string $id)
    {
        $i = SystemIncident::query()->findOrFail($id);

        return AdminApiResponse::success($r, ['incident' => $i, 'notes' => SystemIncidentNote::query()->where('incident_id', $id)->oldest()->get()]);
    }

    public function createIncident(Request $r, AdminAuditLogger $audit)
    {
        $d = $r->validate(['title' => 'required|string|max:180', 'service' => 'required|string|max:60', 'severity' => 'required|in:low,medium,high,critical', 'impact' => 'nullable|string|max:2000', 'assigned_admin_id' => 'nullable|integer|exists:admin_users,id', 'external_reference' => 'nullable|string|max:500', 'started_at' => 'nullable|date', 'reason' => 'required|string|min:4|max:500']);
        if (isset($d['assigned_admin_id']) && ! AdminUser::query()->findOrFail($d['assigned_admin_id'])->isOperationallyActive()) {
            abort(422);
        }$i = SystemIncident::query()->create([...$d, 'started_at' => $d['started_at'] ?? now(), 'created_by_admin_id' => $r->user()->id]);
        unset($i['reason']);
        $audit->write('system_incident.created', $r->user(), $r->attributes->get('admin_session'), 'system_incident', $i->id, reason: $d['reason'], request: $r, after: $i->toArray());

        return AdminApiResponse::success($r, $i, 201);
    }

    public function updateIncident(Request $r, string $id, AdminAuditLogger $audit)
    {
        $i = SystemIncident::query()->findOrFail($id);
        $d = $r->validate(['status' => 'sometimes|in:open,investigating,monitoring,resolved', 'severity' => 'sometimes|in:low,medium,high,critical', 'assigned_admin_id' => 'nullable|integer|exists:admin_users,id', 'impact' => 'nullable|string|max:2000', 'resolution' => 'nullable|string|max:4000', 'reason' => 'required|string|min:4|max:500']);
        $before = $i->toArray();
        if (($d['status'] ?? null) === 'resolved' && ! isset($d['resolution'])) {
            abort(422);
        }$reason = $d['reason'];
        unset($d['reason']);
        if (($d['status'] ?? null) === 'resolved') {
            $d['resolved_at'] = now();
        }$i->fill($d)->save();
        $audit->write('system_incident.updated', $r->user(), $r->attributes->get('admin_session'), 'system_incident', $i->id, reason: $reason, before: $before, after: $i->fresh()->toArray(), request: $r);

        return AdminApiResponse::success($r, $i->fresh());
    }

    public function note(Request $r, string $id, AdminAuditLogger $audit)
    {
        SystemIncident::query()->findOrFail($id);
        $d = $r->validate(['note' => 'required|string|min:2|max:4000']);
        $n = SystemIncidentNote::query()->create(['incident_id' => $id, 'admin_user_id' => $r->user()->id, 'note' => $d['note']]);
        $audit->write('system_incident.note_added', $r->user(), $r->attributes->get('admin_session'), 'system_incident', $id, request: $r, metadata: ['note_id' => $n->id]);

        return AdminApiResponse::success($r, $n, 201);
    }

    public function integrations(Request $r)
    {
        return AdminApiResponse::success($r, ['items' => IntegrationStatus::query()->orderBy('service')->orderBy('provider')->get()]);
    }

    public function upsertIntegration(Request $r, string $service, string $provider, OperationalSanitizer $san, AdminAuditLogger $audit)
    {
        $d = $r->validate(['environment' => 'nullable|string|max:24', 'enabled' => 'required|boolean', 'health' => 'required|in:unknown,healthy,degraded,down', 'public_config' => 'nullable|array', 'last_success_at' => 'nullable|date', 'last_failure_at' => 'nullable|date', 'last_error' => 'nullable|string|max:1000', 'reason' => 'required|string|min:4|max:500']);
        $env = $d['environment'] ?? 'production';
        $existing = IntegrationStatus::query()->where(compact('service', 'provider'))->where('environment', $env)->first();
        $before = $existing?->toArray() ?? [];
        $i = IntegrationStatus::query()->updateOrCreate(['service' => $service, 'provider' => $provider, 'environment' => $env], ['enabled' => $d['enabled'], 'health' => $d['health'], 'public_config' => $san->sanitize($d['public_config'] ?? []), 'last_success_at' => $d['last_success_at'] ?? null, 'last_failure_at' => $d['last_failure_at'] ?? null, 'last_error' => $d['last_error'] ?? null, 'updated_by_admin_id' => $r->user()->id]);
        $audit->write('integration.updated', $r->user(), $r->attributes->get('admin_session'), 'integration', $i->id, reason: $d['reason'], before: $before, after: $i->toArray(), request: $r);

        return AdminApiResponse::success($r, $i);
    }

    public function webhooks(Request $r)
    {
        return AdminApiResponse::success($r, ['items' => WebhookDelivery::query()->latest('created_at')->paginate(min(100, max(1, (int) $r->integer('per_page', 25))))->items()]);
    }

    public function retryWebhook(Request $r, string $id, AdminAuditLogger $audit)
    {
        $w = WebhookDelivery::query()->findOrFail($id);
        $d = $r->validate(['reason' => 'required|string|min:4|max:500']);
        $w->forceFill(['status' => 'retry_requested', 'retry_requested_at' => now()])->save();
        $audit->write('webhook.retry_requested', $r->user(), $r->attributes->get('admin_session'), 'webhook_delivery', $w->id, reason: $d['reason'], request: $r);

        return AdminApiResponse::success($r, $w->fresh(), 202);
    }

    public function alerts(Request $r)
    {
        $q = AdminOperationalAlert::query()->latest();
        if ($r->filled('status')) {
            $q->where('status', $r->string('status'));
        }

return AdminApiResponse::success($r, ['items' => $q->paginate(min(100, max(1, (int) $r->integer('per_page', 25))))->items()]);
    }

    public function acknowledgeAlert(Request $r, string $id, AdminAuditLogger $audit)
    {
        $a = AdminOperationalAlert::query()->findOrFail($id);
        $a->forceFill(['status' => 'acknowledged', 'acknowledged_at' => now(), 'acknowledged_by_admin_id' => $r->user()->id])->save();
        $audit->write('operational_alert.acknowledged', $r->user(), $r->attributes->get('admin_session'), 'operational_alert', $a->id, request: $r);

        return AdminApiResponse::success($r, $a->fresh());
    }

    public function websocket(Request $r)
    {
        $d = $r->validate(['environment' => 'nullable|string|max:24', 'connections' => 'required|integer|min:0', 'subscriptions' => 'required|integer|min:0', 'connect_rate' => 'nullable|integer|min:0', 'disconnect_rate' => 'nullable|integer|min:0', 'reconnect_rate' => 'nullable|integer|min:0', 'fanout_lag_ms' => 'nullable|integer|min:0', 'regions' => 'nullable|array']);
        $s = WebsocketMetricSnapshot::query()->create([...$d, 'captured_at' => now(), 'recorded_by_admin_id' => $r->user()->id]);

        return AdminApiResponse::success($r,$s,201);
    }

    public function security(Request $r,SecuritySummaryService $s)
    {
        return AdminApiResponse::success($r,$s->summary());
    }
}
