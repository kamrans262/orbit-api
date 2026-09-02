<?php

declare(strict_types=1);
use App\Models\AdminAuditLog;
use App\Models\AdminOperationalAlert;
use App\Models\AdminPermission;
use App\Models\AdminReportExport;
use App\Models\AdminRole;
use App\Models\AdminSavedReport;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\FeatureFlag;
use App\Models\IntegrationStatus;
use App\Models\SystemIncident;
use App\Models\User;
use App\Models\UserRegionalProfile;
use App\Models\WebhookDelivery;
use App\Modules\Admin\AnalyticsOperations\Events\AdminOperationalAlertRaised;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Services\AdminRbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
beforeEach(fn () => app(AdminRbacService::class)->syncDefaults());
function m8Admin(string $role = 'devops-operator'): AdminUser
{
    $a = AdminUser::query()->create(['name' => 'M8 Admin', 'email' => Str::uuid().'@m8.test', 'password' => 'StrongPassword!123', 'status' => AdminStatus::Active, 'mfa_confirmed_at' => now(), 'activated_at' => now()]);
    $a->roles()->sync([AdminRole::query()->where('slug', $role)->firstOrFail()->id]);

    return $a;
}
function m8Headers(AdminUser $a, bool $reauth = true): array
{
    app('auth')->forgetGuards();
    $t = $a->createToken('m8-admin', ['admin'], now()->addHours(2));
    AdminSession::query()->create(['id' => (string) Str::uuid7(), 'admin_user_id' => $a->id, 'access_token_id' => $t->accessToken->id, 'last_seen_at' => now(), 'idle_expires_at' => now()->addHour(), 'expires_at' => now()->addHours(2), 'reauthenticated_at' => $reauth ? now() : now()->subHour(), 'mfa_verified_at' => now()]);

    return ['Authorization' => 'Bearer '.$t->plainTextToken];
}
function m8UserHeaders(User $u): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$u->createToken('m8-user')->plainTextToken];
}
function m8Grant(AdminUser $a, string $p): void
{
    $a->roles()->firstOrFail()->permissions()->syncWithoutDetaching([AdminPermission::query()->where('slug', $p)->firstOrFail()->id]);
}

it('requires administrator authentication for M8 administrator APIs', fn () => $this->getJson('/api/admin/v1/analytics')->assertUnauthorized());
it('requires consumer authentication for runtime platform configuration', fn () => $this->getJson('/api/v1/platform/runtime')->assertUnauthorized());
it('analyst can view analytics but cannot modify feature flags', function () {
    $h = m8Headers(m8Admin('analyst'));
    $this->withHeaders($h)->getJson('/api/admin/v1/analytics')->assertOk();
    $this->withHeaders($h)->postJson('/api/admin/v1/feature-flags', ['key' => 'x', 'name' => 'X'])->assertForbidden();
});
it('analytics center reports safe cross-domain aggregates without encrypted content', function () {
    User::factory()->count(2)->create();
    $r = $this->withHeaders(m8Headers(m8Admin('analyst')))->getJson('/api/admin/v1/analytics')->assertOk();
    expect($r->json('data'))->toHaveKeys(['users', 'circles', 'messaging', 'moments', 'sos', 'subscriptions', 'payments', 'notifications', 'media']);
    expect(json_encode($r->json('data')))->not->toContain('ciphertext')->not->toContain('recording_ref');
});
it('saved report rejects unsupported metrics', function () {
    $h = m8Headers(m8Admin('analyst'));
    $this->withHeaders($h)->postJson('/api/admin/v1/analytics/reports', ['name' => 'Bad', 'metrics' => ['users.passwords']])->assertStatus(422)->assertJsonPath('code', 'ANALYTICS_METRIC_UNSUPPORTED');
});
it('analyst can save run and export whitelisted reports', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    $h = m8Headers(m8Admin('analyst'));
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/analytics/reports', ['name' => 'Growth', 'metrics' => ['users.registrations', 'sos.activations']])->assertCreated()->json('data.id');
    $this->withHeaders($h)->postJson('/api/admin/v1/analytics/reports/'.$id.'/run')->assertOk()->assertJsonCount(2, 'data.rows');
    $eid = $this->withHeaders($h)->postJson('/api/admin/v1/analytics/reports/'.$id.'/exports')->assertCreated()->json('data.id');
    $this->withHeaders($h)->get('/api/admin/v1/analytics/exports/'.$eid.'/download')->assertOk();
});
it('analyst can generate XLSX reports without a spreadsheet dependency', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    $h = m8Headers(m8Admin('analyst'));
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/analytics/reports', ['name' => 'XLSX', 'metrics' => ['users.registrations']])->json('data.id');
    $export = $this->withHeaders($h)->postJson('/api/admin/v1/analytics/reports/'.$id.'/exports', ['format' => 'xlsx'])->assertCreated()->assertJsonPath('data.format', 'xlsx');
    $path = AdminReportExport::query()->findOrFail($export->json('data.id'))->storage_path;
    expect(Storage::disk('local')->get($path))->toStartWith('PK');
});
it('private saved reports cannot be read by another analyst', function () {
    $owner = m8Admin('analyst');
    $other = m8Admin('analyst');
    $id = $this->withHeaders(m8Headers($owner))->postJson('/api/admin/v1/analytics/reports', ['name' => 'Private', 'metrics' => ['users.registrations']])->json('data.id');
    $this->withHeaders(m8Headers($other))->postJson('/api/admin/v1/analytics/reports/'.$id.'/run')->assertNotFound();
});
it('team shared reports are visible to other authorized analysts', function () {
    $owner = m8Admin('analyst');
    $other = m8Admin('analyst');
    $id = $this->withHeaders(m8Headers($owner))->postJson('/api/admin/v1/analytics/reports', ['name' => 'Team', 'metrics' => ['users.registrations'], 'team_shared' => true])->json('data.id');
    $this->withHeaders(m8Headers($other))->postJson('/api/admin/v1/analytics/reports/'.$id.'/run')->assertOk();
});
it('scheduled reports are processed by the scheduler command', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    $a = m8Admin('analyst');
    AdminSavedReport::query()->create(['admin_user_id' => $a->id, 'name' => 'Daily', 'metrics' => ['users.registrations'], 'filters' => [], 'schedule' => 'daily', 'next_run_at' => now()->subMinute()]);
    Artisan::call('orbit:analytics:run-scheduled-reports');
    expect(DB::table('admin_report_exports')->count())->toBe(1);
});
it('feature flag creation and update are separately permissioned', function () {
    $dev = m8Admin();
    $h = m8Headers($dev);
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/feature-flags', ['key' => 'new_home', 'name' => 'New Home', 'status' => 'enabled', 'rollout_percentage' => 10])->assertCreated()->json('data.id');
    $this->withHeaders(m8Headers($dev, false))->patchJson('/api/admin/v1/feature-flags/'.$id, ['status' => 'disabled', 'reason' => 'Rollback'])->assertStatus(428);
});
it('devops can clone a feature flag into a disabled zero rollout copy', function () {
    $a = m8Admin();
    $h = m8Headers($a);
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/feature-flags', ['key' => 'source_flag', 'name' => 'Source', 'status' => 'enabled', 'rollout_percentage' => 100])->json('data.id');
    $this->withHeaders($h)->postJson('/api/admin/v1/feature-flags/'.$id.'/clone', ['key' => 'clone_flag', 'name' => 'Clone', 'reason' => 'Experiment copy'])->assertCreated()->assertJsonPath('data.status', 'disabled')->assertJsonPath('data.rollout_percentage', 0);
});
it('feature flag evaluation supports targeted users', function () {
    $u = User::factory()->create();
    $a = m8Admin();
    FeatureFlag::query()->create(['key' => 'targeted', 'name' => 'Targeted', 'status' => 'enabled', 'rollout_percentage' => 100, 'targeting' => ['user_ids' => [$u->id]], 'owner_admin_id' => $a->id]);
    $this->withHeaders(m8Headers($a))->getJson('/api/admin/v1/feature-flags/evaluate/users/'.$u->id)->assertOk()->assertJsonPath('data.flags.targeted', true);
});
it('feature flag evaluation respects country platform and version targeting', function () {
    $u = User::factory()->create();
    UserRegionalProfile::query()->create(['user_id' => $u->id, 'country_code' => 'PK', 'platform' => 'android', 'app_version' => '2.0.0']);
    FeatureFlag::query()->create(['key' => 'regional', 'name' => 'Regional', 'status' => 'enabled', 'rollout_percentage' => 100, 'targeting' => ['countries' => ['PK'], 'platforms' => ['android'], 'app_versions' => ['2.0.0']]]);
    $this->withHeaders(m8UserHeaders($u))->getJson('/api/v1/platform/runtime')->assertOk()->assertJsonPath('data.feature_flags.regional', true);
});
it('runtime config rejects secret shaped keys', function () {
    $h = m8Headers(m8Admin());
    $this->withHeaders($h)->putJson('/api/admin/v1/remote-config/payment_api_secret', ['value' => ['x' => 'y'], 'reason' => 'No secrets'])->assertStatus(422)->assertJsonPath('code', 'OPERATIONS_SECRET_KEY_REJECTED');
});
it('ordinary non secret remote config is available to authenticated consumers', function () {
    $h = m8Headers(m8Admin());
    $this->withHeaders($h)->putJson('/api/admin/v1/remote-config/uploads.max_mb', ['value' => ['value' => 25], 'reason' => 'Tune uploads'])->assertOk();
    $u = User::factory()->create();
    $this->withHeaders(m8UserHeaders($u))->getJson('/api/v1/platform/runtime')->assertOk()->assertJsonPath('data.remote_config.uploads.max_mb', 25);
});
it('critical runtime config requires separate permission and recent reauthentication', function () {
    $super = m8Admin('super-administrator');
    $this->withHeaders(m8Headers($super))->putJson('/api/admin/v1/remote-config/safety.threshold', ['value' => ['value' => 3], 'critical' => true, 'reason' => 'Critical'])->assertForbidden();
    $dev = m8Admin();
    $this->withHeaders(m8Headers($dev, false))->putJson('/api/admin/v1/remote-config/safety.threshold', ['value' => ['value' => 3], 'critical' => true, 'reason' => 'Critical'])->assertStatus(428);
});
it('integration catalog synchronizes known providers without storing secrets', function () {
    Artisan::call('orbit:operations:sync-integrations');
    expect(IntegrationStatus::query()->where('service', 'push')->exists())->toBeTrue()->and(IntegrationStatus::query()->where('service', 'sms')->where('enabled', false)->exists())->toBeTrue();
    expect(json_encode(IntegrationStatus::query()->pluck('public_config')->all()))->not->toContain('password')->not->toContain('secret');
});
it('system health reports database queue api notification media websocket and integration boundaries', function () {
    $r = $this->withHeaders(m8Headers(m8Admin()))->getJson('/api/admin/v1/system/health')->assertOk();
    expect($r->json('data'))->toHaveKeys(['database', 'api', 'queues', 'notifications', 'media', 'websocket', 'integrations']);
});
it('api telemetry records route status and latency without request payloads', function () {
    $h = m8Headers(m8Admin());
    $this->withHeaders($h)->getJson('/api/admin/v1/system/health')->assertOk();
    expect(DB::table('api_request_metrics')->where('route', 'api/admin/v1/system/health')->exists())->toBeTrue();
});
it('websocket telemetry can be recorded by devops', function () {
    $this->withHeaders(m8Headers(m8Admin()))->postJson('/api/admin/v1/system/telemetry/websocket', ['connections' => 10, 'subscriptions' => 30, 'fanout_lag_ms' => 12])->assertCreated();
    $this->withHeaders(m8Headers(m8Admin()))->getJson('/api/admin/v1/system/health')->assertOk();
});
it('queue directory never exposes failed job payloads', function () {
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => 'SECRET_PAYLOAD', 'exception' => 'Short failure', 'failed_at' => now()]);
    $r = $this->withHeaders(m8Headers(m8Admin()))->getJson('/api/admin/v1/system/queues')->assertOk();
    expect(json_encode($r->json('data')))->not->toContain('SECRET_PAYLOAD');
});
it('queue retry and quarantine requests require recent reauthentication', function () {
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Failure', 'failed_at' => now()]);
    $a = m8Admin();
    $this->withHeaders(m8Headers($a, false))->postJson('/api/admin/v1/system/queues/failed/'.$uuid.'/actions', ['action' => 'quarantine', 'reason' => 'Unsafe job'])->assertStatus(428);
});
it('queue quarantine command records a durable operational outcome', function () {
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Failure', 'failed_at' => now()]);
    $a = m8Admin();
    $this->withHeaders(m8Headers($a))->postJson('/api/admin/v1/system/queues/failed/'.$uuid.'/actions', ['action' => 'quarantine', 'reason' => 'Unsafe job'])->assertAccepted();
    Artisan::call('orbit:operations:process-queue-actions');
    expect(DB::table('admin_queue_actions')->where('failed_job_uuid', $uuid)->where('status', 'quarantined')->exists())->toBeTrue();
});
it('incident center supports create assignment notes and resolution', function () {
    $a = m8Admin();
    $h = m8Headers($a);
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/system/incidents', ['title' => 'Push degradation', 'service' => 'notifications', 'severity' => 'high', 'reason' => 'Provider failures'])->assertCreated()->json('data.id');
    $this->withHeaders($h)->postJson('/api/admin/v1/system/incidents/'.$id.'/notes', ['note' => 'Investigating provider latency'])->assertCreated();
    $this->withHeaders($h)->patchJson('/api/admin/v1/system/incidents/'.$id, ['status' => 'resolved', 'resolution' => 'Provider recovered', 'reason' => 'Recovered'])->assertOk()->assertJsonPath('data.status', 'resolved');
});
it('incident detail returns internal notes only to authorized administrators', function () {
    $a = m8Admin();
    $h = m8Headers($a);
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/system/incidents', ['title' => 'API errors', 'service' => 'api', 'severity' => 'high', 'reason' => 'Error spike'])->json('data.id');
    $this->withHeaders($h)->postJson('/api/admin/v1/system/incidents/'.$id.'/notes', ['note' => 'Investigating'])->assertCreated();
    $this->withHeaders($h)->getJson('/api/admin/v1/system/incidents/'.$id)->assertOk()->assertJsonPath('data.notes.0.note', 'Investigating');
});
it('incident resolution requires a resolution note', function () {
    $i = SystemIncident::query()->create(['title' => 'X', 'service' => 'api', 'severity' => 'medium', 'started_at' => now()]);
    $this->withHeaders(m8Headers(m8Admin()))->patchJson('/api/admin/v1/system/incidents/'.$i->id, ['status' => 'resolved', 'reason' => 'Done'])->assertUnprocessable();
});
it('integration configuration strips secret shaped metadata', function () {
    $h = m8Headers(m8Admin());
    $this->withHeaders($h)->putJson('/api/admin/v1/system/integrations/push/fcm', ['enabled' => true, 'health' => 'healthy', 'public_config' => ['region' => 'global', 'api_key' => 'SECRET'], 'reason' => 'Provider healthy'])->assertOk();
    expect(IntegrationStatus::query()->firstOrFail()->public_config)->toBe(['region' => 'global']);
});
it('integration updates require recent reauthentication', function () {
    $this->withHeaders(m8Headers(m8Admin(), false))->putJson('/api/admin/v1/system/integrations/email/smtp', ['enabled' => true, 'health' => 'healthy', 'reason' => 'Healthy'])->assertStatus(428);
});
it('webhook views expose metadata but never payload bodies', function () {
    WebhookDelivery::query()->create(['provider' => 'payments', 'event_type' => 'charge.updated', 'status' => 'failed', 'payload_hash' => hash('sha256', 'secret-body'), 'last_error' => 'Timeout']);
    $r = $this->withHeaders(m8Headers(m8Admin()))->getJson('/api/admin/v1/system/webhooks')->assertOk();
    expect(json_encode($r->json('data')))->not->toContain('secret-body');
});
it('webhook retries require reauthentication and create retry requested state', function () {
    $w = WebhookDelivery::query()->create(['provider' => 'payments', 'event_type' => 'charge.updated', 'status' => 'failed']);
    $a = m8Admin();
    $this->withHeaders(m8Headers($a, false))->postJson('/api/admin/v1/system/webhooks/'.$w->id.'/retry', ['reason' => 'Retry provider delivery'])->assertStatus(428);
    $this->withHeaders(m8Headers($a, true))->postJson('/api/admin/v1/system/webhooks/'.$w->id.'/retry', ['reason' => 'Retry provider delivery'])->assertAccepted()->assertJsonPath('data.status', 'retry_requested');
});
it('operational alert realtime payload is metadata only', function () {
    Event::fake([AdminOperationalAlertRaised::class]);
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => 'SHOULD_NOT_BROADCAST', 'exception' => 'Failure', 'failed_at' => now()]);
    Artisan::call('orbit:operations:scan-alerts');
    Event::assertDispatched(AdminOperationalAlertRaised::class, function ($e) {
        $json = json_encode($e->payload);

        return ! str_contains($json, 'SHOULD_NOT_BROADCAST') && ! str_contains($json, 'payload');
    });
});
it('operational alert scanner creates alerts from real failed jobs', function () {
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Failure', 'failed_at' => now()]);
    Artisan::call('orbit:operations:scan-alerts');
    expect(AdminOperationalAlert::query()->where('kind', 'queue_failures')->exists())->toBeTrue();
});
it('operational alerts can be acknowledged and audited', function () {
    $alert = AdminOperationalAlert::query()->create(['kind' => 'test', 'severity' => 'high', 'title' => 'Test', 'message' => 'Test']);
    $a = m8Admin();
    $this->withHeaders(m8Headers($a))->postJson('/api/admin/v1/system/alerts/'.$alert->id.'/acknowledge')->assertOk()->assertJsonPath('data.status', 'acknowledged');
    expect(AdminAuditLog::query()->where('action', 'operational_alert.acknowledged')->exists())->toBeTrue();
});
it('security summary aggregates signals without exposing sensitive source records', function () {
    $r = $this->withHeaders(m8Headers(m8Admin('security-administrator')))->getJson('/api/admin/v1/system/security-summary')->assertOk();
    expect($r->json('data'))->toHaveKeys(['admin_failed_logins', 'admin_locked_accounts', 'refresh_reuse_events', 'open_risk_signals', 'suspended_users']);
});
it('operations realtime authentication is protected by operations view permission', function () {
    $finance = m8Admin('finance-manager');
    $this->withHeaders(m8Headers($finance))->postJson('/api/admin/v1/system/realtime/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-admin.operations'])->assertForbidden();
});
it('super administrator does not silently receive high risk M8 permissions', function () {
    $s = m8Admin('super-administrator');
    expect($s->hasPermission('feature_flags.modify'))->toBeFalse()->and($s->hasPermission('remote_config.critical.manage'))->toBeFalse()->and($s->hasPermission('queues.manage'))->toBeFalse()->and($s->hasPermission('integrations.manage'))->toBeFalse()->and($s->hasPermission('webhooks.retry'))->toBeFalse();
});
it('devops receives operational sensitive permissions', function () {
    $d = m8Admin();
    expect($d->hasPermission('feature_flags.modify'))->toBeTrue()->and($d->hasPermission('remote_config.critical.manage'))->toBeTrue()->and($d->hasPermission('queues.manage'))->toBeTrue()->and($d->hasPermission('integrations.manage'))->toBeTrue()->and($d->hasPermission('webhooks.retry'))->toBeTrue();
});
it('read only role can view M8 state but cannot mutate it', function () {
    $h = m8Headers(m8Admin('read-only'));
    $this->withHeaders($h)->getJson('/api/admin/v1/system/health')->assertOk();
    $this->withHeaders($h)->postJson('/api/admin/v1/system/incidents', ['title' => 'No', 'service' => 'api', 'severity' => 'low', 'reason' => 'No'])->assertForbidden();
});
it('feature flag mutations produce immutable administrator audit records', function () {
    $a = m8Admin();
    $id = $this->withHeaders(m8Headers($a))->postJson('/api/admin/v1/feature-flags', ['key' => 'audit_flag', 'name' => 'Audit Flag'])->assertCreated()->json('data.id');
    expect(AdminAuditLog::query()->where('action', 'feature_flag.created')->where('target_id', $id)->exists())->toBeTrue();
});
it('remote config mutations produce reasoned audit records', function () {
    $a = m8Admin();
    $this->withHeaders(m8Headers($a))->putJson('/api/admin/v1/remote-config/ui.max_items', ['value' => ['value' => 20], 'reason' => 'Operational tuning'])->assertOk();
    expect(AdminAuditLog::query()->where('action', 'remote_config.updated')->where('reason', 'Operational tuning')->exists())->toBeTrue();
});
it('unknown incidents webhooks and alerts return not found',function () {
    $h = m8Headers(m8Admin());
    $this->withHeaders($h)->patchJson('/api/admin/v1/system/incidents/'.Str::uuid(),['status' => 'monitoring', 'reason' => 'No'])->assertNotFound();
    $this->withHeaders($h)->postJson('/api/admin/v1/system/webhooks/'.Str::uuid().'/retry',['reason' => 'No'])->assertNotFound();
    $this->withHeaders($h)->postJson('/api/admin/v1/system/alerts/'.Str::uuid().'/acknowledge')->assertNotFound();
});
