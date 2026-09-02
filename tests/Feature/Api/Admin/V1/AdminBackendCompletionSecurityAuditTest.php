<?php

declare(strict_types=1);

use App\Models\AdminAuditLog;
use App\Models\AdminIpPolicy;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\Device;
use App\Models\ModerationReport;
use App\Models\PaymentTransaction;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Admin\Services\AdminRbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(AdminRbacService::class)->syncDefaults();
});

function m9Admin(string $role = 'platform-administrator', bool $reauth = true): AdminUser
{
    $admin = AdminUser::query()->create(['name' => 'M9 Admin', 'email' => uniqid('m9').'@example.test', 'password' => 'secret', 'status' => 'active', 'mfa_confirmed_at' => now(), 'activated_at' => now()]);
    $admin->roles()->attach(AdminRole::query()->where('slug', $role)->firstOrFail()->id);

    return $admin;
}

function m9AdminHeaders(AdminUser $admin, bool $reauth = true): array
{
    $token = $admin->createToken('m9-admin', ['admin'])->plainTextToken;
    $access = $admin->tokens()->latest('id')->firstOrFail();
    AdminSession::query()->create(['admin_user_id' => $admin->id, 'access_token_id' => $access->id, 'last_seen_at' => now(), 'idle_expires_at' => now()->addMinutes(30), 'expires_at' => now()->addHour(), 'mfa_verified_at' => now(), 'reauthenticated_at' => $reauth ? now() : now()->subHour()]);

    return ['Authorization' => 'Bearer '.$token];
}

test('M9 admin endpoints require administrator authentication', function (): void {
    $this->getJson('/api/admin/v1/dashboard')->assertUnauthorized();
    $this->getJson('/api/admin/v1/search?q=user')->assertUnauthorized();
    $this->getJson('/api/admin/v1/views')->assertUnauthorized();
    $this->getJson('/api/admin/v1/release/readiness')->assertUnauthorized();
});

test('admin dashboard returns real business and operational aggregates without fake metrics', function (): void {
    User::factory()->count(2)->create();
    $admin = m9Admin('read-only');
    $this->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/dashboard')
        ->assertOk()->assertJsonPath('data.snapshot.business.users.total', 2)
        ->assertJsonStructure(['data' => ['snapshot' => ['business', 'operations', 'environment', 'generated_at']]]);
});

test('admin dashboard layout is personal and audited', function (): void {
    $admin = m9Admin();
    $this->withHeaders(m9AdminHeaders($admin))->putJson('/api/admin/v1/dashboard/layout', ['layout' => [['key' => 'safety', 'visible' => true, 'position' => 0, 'size' => 'large']]])
        ->assertOk()->assertJsonPath('data.0.key', 'safety');
    expect(AdminAuditLog::query()->where('action', 'admin.dashboard.layout.updated')->where('admin_user_id', $admin->id)->exists())->toBeTrue();
});

test('global search is permission filtered and masks email by default', function (): void {
    $user = User::factory()->create(['name' => 'Searchable Orbit User', 'email' => 'searchable@example.test']);
    $admin = m9Admin('support-agent');
    $response = $this->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/search?q=Searchable')->assertOk();
    $response->assertJsonPath('data.results.users.0.id', (string) $user->id);
    expect($response->json('data.results.users.0.secondary'))->toBe('s***@example.test');
    expect($response->json('data.results.payments'))->toBeNull();
});

test('global search never leaks full device identifiers through auxiliary fields', function (): void {
    $user = User::factory()->create();
    $device = Device::query()->create([
        'user_id' => $user->id,
        'client_device_id' => 'm9-private-client-device-987654',
        'name' => 'Searchable Device',
        'platform' => 'android',
    ]);
    $admin = m9Admin('support-agent');

    $result = $this->withHeaders(m9AdminHeaders($admin))
        ->getJson('/api/admin/v1/search?q=Searchable%20Device')
        ->assertOk()
        ->json('data.results.devices.0');

    expect($result)->not->toHaveKey('record_key')
        ->and(json_encode($result))->not->toContain((string) $device->id)
        ->not->toContain('m9-private-client-device-987654');
});

test('payment search masks provider references unless separately revealed', function (): void {
    $user = User::factory()->create();
    PaymentTransaction::query()->create(['user_id' => $user->id, 'provider' => 'manual', 'provider_transaction_ref' => 'provider-secret-reference-123456', 'type' => 'charge', 'amount_minor' => 1000, 'currency' => 'USD', 'status' => 'succeeded', 'occurred_at' => now()]);
    $admin = m9Admin('finance-manager');
    $result = $this->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/search?q=provider-secret')->assertOk()->json('data.results.payments.0');
    expect($result['secondary'])->not->toBe('provider-secret-reference-123456')->and($result['sensitive_masked'])->toBeTrue();
});

test('global search command palette exposes only commands permitted to the current role', function (): void {
    $admin = m9Admin('support-agent');
    $commands = $this->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/search?q=orbit')->assertOk()->json('data.commands');
    expect(collect($commands)->pluck('key'))->toContain('open_reports')->not->toContain('system_health');
});

test('saved views are personal by default and can contain sanitized filters', function (): void {
    $admin = m9Admin('read-only');
    $id = $this->withHeaders(m9AdminHeaders($admin))->postJson('/api/admin/v1/views', ['name' => 'My queue', 'module' => 'reports', 'filters' => ['status' => 'new', 'authorization_token' => 'secret']])
        ->assertCreated()->assertJsonPath('data.scope', 'personal')->json('data.id');
    $this->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/views?module=reports')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.filters.authorization_token', '[REDACTED]');
});

test('saved views cannot be created for modules the administrator cannot access', function (): void {
    $admin = m9Admin('support-agent');
    $this->withHeaders(m9AdminHeaders($admin))
        ->postJson('/api/admin/v1/views', ['name' => 'Finance data', 'module' => 'payments'])
        ->assertForbidden();
});

test('ordinary administrators cannot publish a team saved view', function (): void {
    $admin = m9Admin('support-agent');
    $this->withHeaders(m9AdminHeaders($admin))->postJson('/api/admin/v1/views', ['name' => 'Team queue', 'module' => 'support', 'scope' => 'team'])->assertForbidden();
});

test('platform administrators can publish team views visible to another eligible administrator', function (): void {
    $owner = m9Admin('platform-administrator');
    $other = m9Admin('read-only');
    $this->withHeaders(m9AdminHeaders($owner))->postJson('/api/admin/v1/views', ['name' => 'Shared safety', 'module' => 'sos', 'scope' => 'team'])->assertCreated();
    $this->withHeaders(m9AdminHeaders($other))->getJson('/api/admin/v1/views?module=sos')->assertOk()->assertJsonPath('data.0.name', 'Shared safety');
});

test('one administrator cannot mutate another administrators personal saved view', function (): void {
    $owner = m9Admin();
    $other = m9Admin();
    $id = $this->withHeaders(m9AdminHeaders($owner))->postJson('/api/admin/v1/views', ['name' => 'Private', 'module' => 'users'])->json('data.id');
    $this->withHeaders(m9AdminHeaders($other))->patchJson('/api/admin/v1/views/'.$id, ['name' => 'Hijacked'])->assertForbidden();
});

test('bulk report assignment reuses the real moderation workflow', function (): void {
    $moderator = m9Admin('moderator');
    $report = ModerationReport::query()->create(['reporter_user_id' => User::factory()->create()->id, 'target_type' => 'user', 'target_id' => '1', 'reason' => 'spam', 'status' => 'new', 'priority' => 'normal']);
    $this->withHeaders(m9AdminHeaders($moderator))->postJson('/api/admin/v1/bulk/reports/assign', ['report_ids' => [$report->id], 'assigned_admin_id' => $moderator->id, 'reason' => 'Bulk queue ownership'])->assertOk()->assertJsonPath('data.updated', 1);
    expect($report->refresh()->assigned_admin_id)->toBe($moderator->id);
});

test('bulk support assignment reuses support assignee eligibility checks', function (): void {
    $agent = m9Admin('support-agent');
    $ticket = SupportTicket::query()->create(['user_id' => User::factory()->create()->id, 'category' => 'technical', 'priority' => 'normal', 'status' => 'new', 'subject' => 'Help', 'sla_due_at' => now()->addDay(), 'last_message_at' => now()]);
    $this->withHeaders(m9AdminHeaders($agent))->postJson('/api/admin/v1/bulk/support/assign', ['ticket_ids' => [$ticket->id], 'assigned_admin_id' => $agent->id, 'reason' => 'Bulk queue ownership'])->assertOk();
    expect($ticket->refresh()->assigned_admin_id)->toBe($agent->id);
});

test('release readiness endpoint is permissioned and never returns secret values', function (): void {
    $admin = m9Admin('security-administrator');
    $response = $this->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/release/readiness')->assertOk();
    expect(json_encode($response->json()))->not->toContain((string) config('app.key'))->not->toContain('REVERB_APP_SECRET');
});

test('release readiness detects active administrators without MFA as blocking', function (): void {
    AdminUser::query()->create(['email' => 'unsafe-admin@example.test', 'password' => 'secret', 'status' => 'active', 'activated_at' => now()]);
    $admin = m9Admin('security-administrator');
    $checks = collect($this->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/release/readiness')->assertOk()->json('data.checks'));
    expect($checks->firstWhere('key', 'admin_mfa')['status'])->toBe('fail');
});

test('release readiness keeps sensitive permission separation out of super administrator', function (): void {
    $super = m9Admin('super-administrator');
    expect($super->hasPermission('security.ip_policies.manage'))->toBeFalse();
    $admin = m9Admin('security-administrator');
    $checks = collect($this->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/release/readiness')->assertOk()->json('data.checks'));
    expect($checks->firstWhere('key', 'sensitive_permission_separation')['status'])->toBe('pass');
});

test('only security administrators receive IP policy management by default', function (): void {
    expect(m9Admin('security-administrator')->hasPermission('security.ip_policies.manage'))->toBeTrue();
    expect(m9Admin('super-administrator')->hasPermission('security.ip_policies.manage'))->toBeFalse();
});

test('IP policy creation requires recent reauthentication', function (): void {
    $security = m9Admin('security-administrator');
    $target = m9Admin('platform-administrator');
    $this->withHeaders(m9AdminHeaders($security, false))->postJson('/api/admin/v1/security/ip-policies', ['admin_user_id' => $target->id, 'cidr' => '127.0.0.1/32', 'reason' => 'Restrict privileged login'])->assertStatus(428);
});

test('invalid IP policy networks fail validation without changing access state', function (): void {
    $security = m9Admin('security-administrator');
    $target = m9Admin();
    $this->withHeaders(m9AdminHeaders($security))->postJson('/api/admin/v1/security/ip-policies', ['admin_user_id' => $target->id, 'cidr' => 'not-an-ip/99', 'reason' => 'Restrict login'])->assertUnprocessable();
    expect(AdminIpPolicy::query()->count())->toBe(0);
});

test('enabled administrator IP policies block credentials from disallowed networks', function (): void {
    $admin = m9Admin('platform-administrator');
    AdminIpPolicy::query()->create(['admin_user_id' => $admin->id, 'cidr' => '10.0.0.0/8', 'enabled' => true]);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/dashboard')->assertForbidden()->assertJsonPath('code', 'ADMIN_IP_NOT_ALLOWED');
});

test('enabled administrator IP policies accept an address inside the configured CIDR', function (): void {
    $admin = m9Admin('platform-administrator');
    AdminIpPolicy::query()->create(['admin_user_id' => $admin->id, 'cidr' => '127.0.0.0/8', 'enabled' => true]);
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(m9AdminHeaders($admin))->getJson('/api/admin/v1/dashboard')->assertOk();
});

test('admin IP policy changes are reason audited', function (): void {
    $security = m9Admin('security-administrator');
    $target = m9Admin();
    $this->withHeaders(m9AdminHeaders($security))->postJson('/api/admin/v1/security/ip-policies', ['admin_user_id' => $target->id, 'cidr' => '127.0.0.1/32', 'reason' => 'Privileged network restriction'])->assertCreated();
    expect(AdminAuditLog::query()->where('action', 'admin.security.ip_policy.created')->where('reason', 'Privileged network restriction')->exists())->toBeTrue();
});

test('release audit CLI command is registered and emits structured output', function (): void {
    $exit = Artisan::call('orbit:release:audit', ['--json' => true]);
    expect($exit)->toBeIn([0, 1]);
    expect(Artisan::output())->toContain('"checks"');
});

test('M9 default RBAC includes dashboard search saved views and release audit for read only administrators', function (): void {
    $admin = m9Admin('read-only');
    foreach (['dashboard.view', 'global_search.use', 'views.view', 'views.manage', 'release.audit.view'] as $permission) {
        expect($admin->hasPermission($permission))->toBeTrue();
    }
});

test('consumer bearer credentials cannot authenticate to M9 administrator endpoints', function (): void {
    $user = User::factory()->create();
    $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('consumer')->plainTextToken])->getJson('/api/admin/v1/dashboard')->assertUnauthorized();
});

test('administrator bearer credentials cannot authenticate to completed consumer dashboard contracts', function (): void {
    $admin = m9Admin();
    $this->withHeaders(m9AdminHeaders($admin))->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
});

test('request ids are echoed across M9 admin APIs', function (): void {
    $admin = m9Admin();
    $id = 'm9-admin-request-12345';
    $this->withHeaders([...m9AdminHeaders($admin), 'X-Request-Id' => $id])->getJson('/api/admin/v1/dashboard')->assertOk()->assertHeader('X-Request-Id', $id)->assertJsonPath('request_id', $id);
});
