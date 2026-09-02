<?php

declare(strict_types=1);

use App\Models\AdminAuditLog;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Models\BillingPlan;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateTranslation;
use App\Models\ContentItem;
use App\Models\Device;
use App\Models\LegalDocument;
use App\Models\LegalDocumentTranslation;
use App\Models\MaintenanceWindow;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\OrbitNotification;
use App\Models\User;
use App\Models\UserRegionalProfile;
use App\Models\UserSubscription;
use App\Modules\Admin\BillingAdvertising\Services\BillingCatalogService;
use App\Modules\Admin\CommunicationsContent\Services\AudienceResolver;
use App\Modules\Admin\CommunicationsContent\Services\RegionalPlatformService;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Services\AdminRbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(AdminRbacService::class)->syncDefaults();
    app(BillingCatalogService::class)->syncDefaults();
});

function m7Admin(string $role = 'marketing-manager'): AdminUser
{
    app(AdminRbacService::class)->syncDefaults();

    $admin = AdminUser::query()->create([
        'name' => 'M7 Administrator',
        'email' => Str::uuid().'@m7.orbit.test',
        'password' => 'StrongPassword!123',
        'status' => AdminStatus::Active,
        'mfa_confirmed_at' => now(),
        'activated_at' => now(),
    ]);

    $admin->roles()->sync([
        AdminRole::query()->where('slug', $role)->firstOrFail()->id,
    ]);

    return $admin;
}

function m7AdminHeaders(AdminUser $admin, bool $reauthenticated = true): array
{
    app('auth')->forgetGuards();
    $token = $admin->createToken('m7-admin', ['admin'], now()->addHours(2));

    AdminSession::query()->create([
        'id' => (string) Str::uuid7(),
        'admin_user_id' => $admin->id,
        'access_token_id' => $token->accessToken->id,
        'last_seen_at' => now(),
        'idle_expires_at' => now()->addHour(),
        'expires_at' => now()->addHours(2),
        'reauthenticated_at' => $reauthenticated ? now() : now()->subHour(),
        'mfa_verified_at' => now(),
    ]);

    return ['Authorization' => 'Bearer '.$token->plainTextToken];
}

function m7UserHeaders(User $user): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('m7-user')->plainTextToken];
}

function m7Grant(AdminUser $admin, string $permission): void
{
    $role = $admin->roles()->firstOrFail();
    $permissionId = AdminPermission::query()->where('slug', $permission)->firstOrFail()->id;
    $role->permissions()->syncWithoutDetaching([$permissionId]);
}

function m7Campaign(string $channel, array $audience, array $overrides = []): CommunicationCampaign
{
    return CommunicationCampaign::query()->create([
        'name' => 'M7 Campaign '.Str::random(6),
        'channel' => $channel,
        'category' => 'product',
        'priority' => 'normal',
        'status' => 'draft',
        'locale' => 'en',
        'title' => 'Orbit update',
        'body' => 'A safe administrative communication.',
        'audience' => $audience,
        ...$overrides,
    ]);
}

function m7PushDevice(User $user, string $platform = 'ios'): Device
{
    return Device::query()->create([
        'user_id' => $user->id,
        'client_device_id' => 'm7-'.Str::uuid(),
        'name' => 'M7 Device',
        'platform' => $platform,
        'app_version' => '2.0.0',
        'push_token' => 'm7-push-'.Str::uuid(),
        'last_seen_at' => now(),
    ]);
}

it('requires administrator authentication for communications content and regional APIs', function (): void {
    $this->getJson('/api/admin/v1/communications/campaigns')->assertUnauthorized();
    $this->getJson('/api/admin/v1/templates')->assertUnauthorized();
    $this->getJson('/api/admin/v1/announcements')->assertUnauthorized();
    $this->getJson('/api/admin/v1/regions')->assertUnauthorized();
    $this->getJson('/api/admin/v1/maintenance')->assertUnauthorized();
});

it('keeps platform config public while protecting consumer content APIs', function (): void {
    $this->getJson('/api/v1/platform/config')->assertOk()->assertJsonPath('data.maintenance.sos_available', true);
    $this->getJson('/api/v1/communications/announcements')->assertUnauthorized();
    $this->getJson('/api/v1/legal/documents')->assertUnauthorized();
    $this->putJson('/api/v1/platform/profile', ['country_code' => 'PK'])->assertUnauthorized();
});

it('read only administrators can view M7 modules but cannot mutate them', function (): void {
    $headers = m7AdminHeaders(m7Admin('read-only'));

    $this->withHeaders($headers)->getJson('/api/admin/v1/communications/campaigns')->assertOk();
    $this->withHeaders($headers)->getJson('/api/admin/v1/templates')->assertOk();
    $this->withHeaders($headers)->getJson('/api/admin/v1/content')->assertOk();
    $this->withHeaders($headers)->postJson('/api/admin/v1/templates', [
        'slug' => 'read-only-test', 'channel' => 'push',
    ])->assertForbidden();
});

it('default RBAC keeps emergency and critical configuration permissions separately protected', function (): void {
    $super = m7Admin('super-administrator');
    $marketing = m7Admin('marketing-manager');
    $compliance = m7Admin('compliance-officer');
    $devops = m7Admin('devops-operator');

    expect($super->hasPermission('communications.emergency.send'))->toBeFalse()
        ->and($super->hasPermission('legal.manage'))->toBeFalse()
        ->and($super->hasPermission('regions.manage'))->toBeFalse()
        ->and($super->hasPermission('app_versions.manage'))->toBeFalse()
        ->and($super->hasPermission('maintenance.manage'))->toBeFalse()
        ->and($marketing->hasPermission('communications.manage'))->toBeTrue()
        ->and($marketing->hasPermission('communications.emergency.send'))->toBeFalse()
        ->and($compliance->hasPermission('legal.manage'))->toBeTrue()
        ->and($compliance->hasPermission('regions.manage'))->toBeTrue()
        ->and($devops->hasPermission('app_versions.manage'))->toBeTrue()
        ->and($devops->hasPermission('maintenance.manage'))->toBeTrue();
});

it('template publication requires a reviewed translation', function (): void {
    $headers = m7AdminHeaders(m7Admin());
    $id = $this->withHeaders($headers)->postJson('/api/admin/v1/templates', [
        'slug' => 'account-notice', 'channel' => 'in_app', 'variables' => ['name'],
    ])->assertCreated()->json('data.id');

    $this->withHeaders($headers)->putJson('/api/admin/v1/templates/'.$id.'/translations/en', [
        'status' => 'draft', 'title' => 'Hello {{ name }}', 'body' => 'Welcome {{ name }}',
    ])->assertOk();

    $this->withHeaders($headers)->postJson('/api/admin/v1/templates/'.$id.'/publish')
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONTENT_REVIEW_REQUIRED');
});

it('reviewed templates publish and render declared variables safely', function (): void {
    $headers = m7AdminHeaders(m7Admin());
    $id = $this->withHeaders($headers)->postJson('/api/admin/v1/templates', [
        'slug' => 'security-alert', 'channel' => 'email', 'variables' => ['name', 'device'],
    ])->assertCreated()->json('data.id');

    $this->withHeaders($headers)->putJson('/api/admin/v1/templates/'.$id.'/translations/en', [
        'status' => 'review',
        'subject' => 'Security alert for {{ name }}',
        'title' => 'New sign-in',
        'body' => '{{ name }}, a sign-in was detected on {{ device }}.',
    ])->assertOk()->assertJsonPath('data.status', 'review');

    $this->withHeaders($headers)->postJson('/api/admin/v1/templates/'.$id.'/publish')
        ->assertOk()->assertJsonPath('data.status', 'published');

    $this->withHeaders($headers)->postJson('/api/admin/v1/templates/'.$id.'/preview', [
        'locale' => 'en', 'variables' => ['name' => 'Ayesha', 'device' => 'Android'],
    ])->assertOk()
        ->assertJsonPath('data.subject', 'Security alert for Ayesha')
        ->assertJsonPath('data.body', 'Ayesha, a sign-in was detected on Android.');
});

it('template preview rejects missing declared variables', function (): void {
    $admin = m7Admin();
    $template = CommunicationTemplate::query()->create([
        'slug' => 'missing-variable', 'channel' => 'push', 'status' => 'published',
        'variables' => ['name'], 'created_by_admin_id' => $admin->id,
    ]);
    CommunicationTemplateTranslation::query()->create([
        'template_id' => $template->id, 'locale' => 'en', 'status' => 'published',
        'title' => 'Hello {{ name }}', 'body' => 'Hi {{ name }}', 'published_at' => now(),
    ]);

    $this->withHeaders(m7AdminHeaders($admin))->postJson('/api/admin/v1/templates/'.$template->id.'/preview', [
        'variables' => [],
    ])->assertUnprocessable()->assertJsonPath('code', 'TEMPLATE_VARIABLES_MISSING');
});

it('campaign preview resolves selected users without leaking an unbounded audience', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();
    User::factory()->create();
    $headers = m7AdminHeaders(m7Admin());

    $id = $this->withHeaders($headers)->postJson('/api/admin/v1/communications/campaigns', [
        'name' => 'Selected audience', 'channel' => 'in_app', 'title' => 'Hello', 'body' => 'Selected users only',
        'audience' => ['mode' => 'selected', 'user_ids' => [$one->id, $two->id]],
    ])->assertCreated()->json('data.id');

    $this->withHeaders($headers)->getJson('/api/admin/v1/communications/campaigns/'.$id.'/preview')
        ->assertOk()
        ->assertJsonPath('data.audience.target_count', 2)
        ->assertJsonCount(2, 'data.audience.sample_user_ids');
});

it('audience targeting supports implicit free tier plus country platform and app version', function (): void {
    $free = User::factory()->create();
    $plus = User::factory()->create();
    $other = User::factory()->create();
    $plusPlan = BillingPlan::query()->where('slug', 'plus')->firstOrFail();

    UserSubscription::query()->create([
        'user_id' => $plus->id, 'plan_id' => $plusPlan->id, 'status' => 'active', 'source' => 'admin',
        'provider' => 'manual', 'price_amount_minor' => 1000, 'price_currency' => 'USD',
        'billing_interval' => 'monthly', 'started_at' => now(),
    ]);
    UserRegionalProfile::query()->create(['user_id' => $free->id, 'country_code' => 'PK', 'platform' => 'android', 'app_version' => '2.0.0', 'locale' => 'en']);
    UserRegionalProfile::query()->create(['user_id' => $plus->id, 'country_code' => 'PK', 'platform' => 'android', 'app_version' => '2.0.0', 'locale' => 'en']);
    UserRegionalProfile::query()->create(['user_id' => $other->id, 'country_code' => 'US', 'platform' => 'ios', 'app_version' => '1.0.0', 'locale' => 'en']);

    $resolver = app(AudienceResolver::class);
    expect($resolver->userIds(['plans' => ['free'], 'countries' => ['PK'], 'platforms' => ['android'], 'app_versions' => ['2.0.0']]))->toBe([$free->id])
        ->and($resolver->userIds(['plans' => ['plus'], 'countries' => ['PK']]))->toBe([$plus->id]);
});

it('in app campaigns create safe Orbit notification metadata without copying body into payload', function (): void {
    $user = User::factory()->create();
    $campaign = m7Campaign('in_app', ['mode' => 'selected', 'user_ids' => [$user->id]]);

    $this->withHeaders(m7AdminHeaders(m7Admin()))->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')
        ->assertOk()->assertJsonPath('data.status', 'sent');

    $notification = OrbitNotification::query()->sole();
    expect($notification->payload)->toBe(['resource_id' => $campaign->id])
        ->and(json_encode($notification->payload))->not->toContain($campaign->body);
    expect(CommunicationDelivery::query()->where('campaign_id', $campaign->id)->value('status'))->toBe('delivered');
});

it('push campaigns stop at the durable provider boundary and never store raw push tokens in payloads', function (): void {
    $user = User::factory()->create();
    $device = m7PushDevice($user, 'android');
    $campaign = m7Campaign('push', ['mode' => 'selected', 'user_ids' => [$user->id]]);

    $this->withHeaders(m7AdminHeaders(m7Admin()))->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')->assertOk();

    $delivery = NotificationDelivery::query()->sole();
    expect($delivery->status)->toBe('pending_provider')
        ->and($delivery->provider)->toBe('fcm')
        ->and(json_encode($delivery->payload))->not->toContain((string) $device->push_token);
    expect(CommunicationDelivery::query()->where('campaign_id', $campaign->id)->value('status'))->toBe('pending_provider');
});

it('ordinary campaigns respect disabled push preference', function (): void {
    $user = User::factory()->create();
    m7PushDevice($user);
    NotificationPreference::query()->create(['user_id' => $user->id, 'push_enabled' => false, 'in_app_enabled' => true]);
    $campaign = m7Campaign('push', ['mode' => 'selected', 'user_ids' => [$user->id]]);

    $this->withHeaders(m7AdminHeaders(m7Admin()))->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')->assertOk();

    expect(NotificationDelivery::query()->count())->toBe(0)
        ->and(CommunicationDelivery::query()->where('campaign_id', $campaign->id)->value('status'))->toBe('suppressed_preference');
});

it('emergency campaigns require a separately assigned permission', function (): void {
    $user = User::factory()->create();
    $campaign = m7Campaign('in_app', ['mode' => 'selected', 'user_ids' => [$user->id]], ['is_emergency' => true, 'priority' => 'highest']);

    $this->withHeaders(m7AdminHeaders(m7Admin()))->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')
        ->assertForbidden()->assertJsonPath('code', 'COMMUNICATION_EMERGENCY_FORBIDDEN');
});

it('emergency campaigns require recent reauthentication after permission assignment', function (): void {
    $user = User::factory()->create();
    $admin = m7Admin();
    m7Grant($admin, 'communications.emergency.send');
    $campaign = m7Campaign('in_app', ['mode' => 'selected', 'user_ids' => [$user->id]], ['is_emergency' => true]);

    $this->withHeaders(m7AdminHeaders($admin, false))->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')
        ->assertStatus(428)->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');
});

it('authorized emergency push bypasses ordinary notification preference but remains provider pending', function (): void {
    $user = User::factory()->create();
    m7PushDevice($user);
    NotificationPreference::query()->create(['user_id' => $user->id, 'push_enabled' => false, 'in_app_enabled' => false]);
    $admin = m7Admin();
    m7Grant($admin, 'communications.emergency.send');
    $campaign = m7Campaign('push', ['mode' => 'selected', 'user_ids' => [$user->id]], ['is_emergency' => true, 'priority' => 'highest']);

    $this->withHeaders(m7AdminHeaders($admin))->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')->assertOk();

    expect(NotificationDelivery::query()->sole()->priority)->toBe('highest')
        ->and(CommunicationDelivery::query()->sole()->status)->toBe('pending_provider');
});

it('email campaigns dispatch through Laravel Mail and refresh live delivery statistics', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'm7-email@example.test']);
    $campaign = m7Campaign('email', ['mode' => 'selected', 'user_ids' => [$user->id]], ['subject' => 'Orbit email']);
    $headers = m7AdminHeaders(m7Admin());

    $this->withHeaders($headers)->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')->assertOk();
    expect(CommunicationDelivery::query()->sole()->status)->toBe('pending_provider');

    $this->artisan('orbit:communications:dispatch-provider-deliveries')->assertSuccessful();
    expect(CommunicationDelivery::query()->sole()->status)->toBe('dispatched');

    $this->withHeaders($headers)->getJson('/api/admin/v1/communications/campaigns/'.$campaign->id)
        ->assertOk()->assertJsonPath('data.stats.delivered', 1);
});

it('sms campaigns report provider unconfigured instead of claiming a send', function (): void {
    $user = User::factory()->create();
    $campaign = m7Campaign('sms', ['mode' => 'selected', 'user_ids' => [$user->id]]);

    $this->withHeaders(m7AdminHeaders(m7Admin()))->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')->assertOk();

    $delivery = CommunicationDelivery::query()->sole();
    expect($delivery->status)->toBe('provider_unconfigured')
        ->and($delivery->delivered_at)->toBeNull();
});

it('scheduled campaigns are dispatched by the scheduler command', function (): void {
    $user = User::factory()->create();
    $campaign = m7Campaign('in_app', ['mode' => 'selected', 'user_ids' => [$user->id]], [
        'status' => 'scheduled', 'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('orbit:communications:process-scheduled')->assertSuccessful();

    expect($campaign->refresh()->status)->toBe('sent')
        ->and(CommunicationDelivery::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
});

it('cancelled communication campaigns are final and cannot be sent', function (): void {
    $campaign = m7Campaign('in_app', ['mode' => 'all']);
    $headers = m7AdminHeaders(m7Admin());

    $this->withHeaders($headers)->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/cancel')
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->withHeaders($headers)->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')
        ->assertStatus(409)->assertJsonPath('code', 'COMMUNICATION_CAMPAIGN_FINAL');
});

it('system banner campaigns create a published targeted announcement', function (): void {
    $user = User::factory()->create();
    $campaign = m7Campaign('system_banner', ['mode' => 'selected', 'user_ids' => [$user->id]], ['title' => 'Maintenance notice']);

    $this->withHeaders(m7AdminHeaders(m7Admin()))->postJson('/api/admin/v1/communications/campaigns/'.$campaign->id.'/send')->assertOk();

    expect(Announcement::query()->whereKey($campaign->id)->value('status'))->toBe('published');
    $this->withHeaders(m7UserHeaders($user))->getJson('/api/v1/communications/announcements')
        ->assertOk()->assertJsonPath('data.0.title', 'Maintenance notice');
});

it('announcement review publication and audience targeting are consumer safe', function (): void {
    $target = User::factory()->create();
    $other = User::factory()->create();
    $headers = m7AdminHeaders(m7Admin());

    $id = $this->withHeaders($headers)->postJson('/api/admin/v1/announcements', [
        'type' => 'product', 'audience' => ['mode' => 'selected', 'user_ids' => [$target->id]], 'priority' => 'high',
    ])->assertCreated()->json('data.id');
    $this->withHeaders($headers)->putJson('/api/admin/v1/announcements/'.$id.'/translations/en', [
        'status' => 'review', 'title' => 'New Orbit feature', 'body' => 'A reviewed product announcement.',
    ])->assertOk();
    $this->withHeaders($headers)->postJson('/api/admin/v1/announcements/'.$id.'/publish')->assertOk();

    $this->withHeaders(m7UserHeaders($target))->getJson('/api/v1/communications/announcements')->assertOk()->assertJsonCount(1, 'data');
    $this->withHeaders(m7UserHeaders($other))->getJson('/api/v1/communications/announcements')->assertOk()->assertJsonCount(0, 'data');
});

it('security announcements require emergency permission and recent reauthentication before publication', function (): void {
    $admin = m7Admin();
    $headers = m7AdminHeaders($admin, true);
    $id = $this->withHeaders($headers)->postJson('/api/admin/v1/announcements', [
        'type' => 'security', 'priority' => 'highest', 'audience' => ['mode' => 'all'],
    ])->assertCreated()->json('data.id');
    $this->withHeaders($headers)->putJson('/api/admin/v1/announcements/'.$id.'/translations/en', [
        'status' => 'review', 'title' => 'Security notice', 'body' => 'Sensitive customer security communication.',
    ])->assertOk();

    $this->withHeaders($headers)->postJson('/api/admin/v1/announcements/'.$id.'/publish')
        ->assertForbidden()->assertJsonPath('code', 'COMMUNICATION_EMERGENCY_FORBIDDEN');

    m7Grant($admin, 'communications.emergency.send');
    $this->withHeaders(m7AdminHeaders($admin, false))->postJson('/api/admin/v1/announcements/'.$id.'/publish')
        ->assertStatus(428)->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');
    $this->withHeaders(m7AdminHeaders($admin, true))->postJson('/api/admin/v1/announcements/'.$id.'/publish')
        ->assertOk()->assertJsonPath('data.status', 'published');
});

it('announcement localization falls back to published English copy', function (): void {
    $user = User::factory()->create(['locale' => 'fr']);
    $announcement = Announcement::query()->create([
        'type' => 'security', 'status' => 'published', 'priority' => 'high', 'dismissible' => true,
        'audience' => ['mode' => 'all'], 'published_at' => now(),
    ]);
    AnnouncementTranslation::query()->create([
        'announcement_id' => $announcement->id, 'locale' => 'en', 'status' => 'published',
        'title' => 'Security notice', 'body' => 'English fallback', 'published_at' => now(),
    ]);

    $this->withHeaders(m7UserHeaders($user))->getJson('/api/v1/communications/announcements?locale=fr')
        ->assertOk()->assertJsonPath('data.0.body', 'English fallback');
});

it('regional CMS content uses consumer profile country and locale fallback', function (): void {
    $user = User::factory()->create();
    $outside = User::factory()->create();
    UserRegionalProfile::query()->create(['user_id' => $user->id, 'country_code' => 'PK', 'platform' => 'android', 'app_version' => '2.0.0', 'locale' => 'ur']);
    UserRegionalProfile::query()->create(['user_id' => $outside->id, 'country_code' => 'US', 'platform' => 'ios', 'app_version' => '2.0.0', 'locale' => 'en']);
    $headers = m7AdminHeaders(m7Admin());

    $id = $this->withHeaders($headers)->postJson('/api/admin/v1/content', [
        'type' => 'safety', 'slug' => 'pakistan-safety', 'regions' => ['PK'],
    ])->assertCreated()->json('data.id');
    $this->withHeaders($headers)->putJson('/api/admin/v1/content/'.$id.'/translations/en', [
        'status' => 'review', 'title' => 'Safety', 'body' => 'Regional safety guidance.',
    ])->assertOk();
    $this->withHeaders($headers)->postJson('/api/admin/v1/content/'.$id.'/publish')->assertOk();

    $this->withHeaders(m7UserHeaders($user))->getJson('/api/v1/content/pakistan-safety')
        ->assertOk()->assertJsonPath('data.body', 'Regional safety guidance.');
    $this->withHeaders(m7UserHeaders($outside))->getJson('/api/v1/content/pakistan-safety')->assertNotFound();
});

it('future CMS publication is scheduled and the due scheduler publishes it', function (): void {
    $headers = m7AdminHeaders(m7Admin());
    $id = $this->withHeaders($headers)->postJson('/api/admin/v1/content', [
        'type' => 'release_announcement', 'slug' => 'future-release', 'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertCreated()->json('data.id');
    $this->withHeaders($headers)->putJson('/api/admin/v1/content/'.$id.'/translations/en', [
        'status' => 'review', 'title' => 'Future release', 'body' => 'Scheduled content.',
    ])->assertOk();

    $this->withHeaders($headers)->postJson('/api/admin/v1/content/'.$id.'/publish')
        ->assertOk()->assertJsonPath('data.status', 'scheduled');

    ContentItem::query()->whereKey($id)->update(['scheduled_at' => now()->subMinute()]);
    $this->artisan('orbit:content:publish-scheduled')->assertSuccessful();
    expect(ContentItem::query()->findOrFail($id)->status)->toBe('published');
});

it('legal publication requires recent reauthentication for compliance administrators', function (): void {
    $admin = m7Admin('compliance-officer');
    $fresh = m7AdminHeaders($admin, true);
    $id = $this->withHeaders($fresh)->postJson('/api/admin/v1/legal/documents', [
        'document_type' => 'privacy_policy', 'version' => '2026.09', 'requires_reacceptance' => true,
    ])->assertCreated()->json('data.id');
    $this->withHeaders($fresh)->putJson('/api/admin/v1/legal/documents/'.$id.'/translations/en', [
        'status' => 'review', 'title' => 'Privacy Policy', 'body' => 'Reviewed legal text.',
    ])->assertOk();

    $this->withHeaders(m7AdminHeaders($admin, false))->postJson('/api/admin/v1/legal/documents/'.$id.'/publish')
        ->assertStatus(428)->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');
    $this->withHeaders(m7AdminHeaders($admin, true))->postJson('/api/admin/v1/legal/documents/'.$id.'/publish')
        ->assertOk()->assertJsonPath('data.status', 'published');
});

it('duplicate legal document versions fail cleanly instead of surfacing a database exception', function (): void {
    $headers = m7AdminHeaders(m7Admin('compliance-officer'));
    $payload = ['document_type' => 'terms', 'version' => '3.0'];

    $this->withHeaders($headers)->postJson('/api/admin/v1/legal/documents', $payload)->assertCreated();
    $this->withHeaders($headers)->postJson('/api/admin/v1/legal/documents', $payload)
        ->assertStatus(409)->assertJsonPath('code', 'LEGAL_VERSION_EXISTS');
});

it('legal acceptance is effective region scoped idempotent and reflected to the consumer', function (): void {
    $pk = User::factory()->create();
    $us = User::factory()->create();
    UserRegionalProfile::query()->create(['user_id' => $pk->id, 'country_code' => 'PK', 'locale' => 'en']);
    UserRegionalProfile::query()->create(['user_id' => $us->id, 'country_code' => 'US', 'locale' => 'en']);
    $document = LegalDocument::query()->create([
        'document_type' => 'regional_notice', 'version' => 'PK-1', 'status' => 'published',
        'regions' => ['PK'], 'requires_reacceptance' => true, 'effective_at' => now()->subMinute(), 'published_at' => now(),
    ]);
    LegalDocumentTranslation::query()->create([
        'legal_document_id' => $document->id, 'locale' => 'en', 'status' => 'published',
        'title' => 'Pakistan notice', 'body' => 'Regional legal notice.', 'published_at' => now(),
    ]);

    $pkHeaders = m7UserHeaders($pk);
    $this->withHeaders($pkHeaders)->getJson('/api/v1/legal/documents')->assertOk()->assertJsonPath('data.0.accepted', false);
    $this->withHeaders($pkHeaders)->postJson('/api/v1/legal/documents/'.$document->id.'/accept')->assertOk();
    $this->withHeaders($pkHeaders)->postJson('/api/v1/legal/documents/'.$document->id.'/accept')->assertOk();
    $this->withHeaders($pkHeaders)->getJson('/api/v1/legal/documents')->assertOk()->assertJsonPath('data.0.accepted', true);
    $this->withHeaders(m7UserHeaders($us))->postJson('/api/v1/legal/documents/'.$document->id.'/accept')->assertNotFound();
});

it('regional configuration requires compliance reauthentication and is exposed through safe platform config', function (): void {
    $admin = m7Admin('compliance-officer');
    $payload = [
        'feature_availability' => ['moments' => true],
        'sms_available' => false,
        'emergency_information' => ['number' => '1122'],
        'consent_requirements' => ['analytics' => true],
    ];

    $this->withHeaders(m7AdminHeaders($admin, false))->putJson('/api/admin/v1/regions/PK', $payload)->assertStatus(428);
    $this->withHeaders(m7AdminHeaders($admin, true))->putJson('/api/admin/v1/regions/PK', $payload)->assertOk();

    // Laravel's feature-test client keeps default headers set by withHeaders().
    // Clear the admin bearer token before exercising the intentionally public
    // platform configuration endpoint; consumer/admin token isolation should
    // continue rejecting admin credentials on consumer routes.
    $this->withHeaders(['Authorization' => '']);

    $this->getJson('/api/v1/platform/config?country=PK')
        ->assertOk()
        ->assertJsonPath('data.region.country_code', 'PK')
        ->assertJsonPath('data.region.sms_available', false)
        ->assertJsonPath('data.maintenance.sos_available', true);
});

it('consumer regional profile partial updates preserve values that were not supplied', function (): void {
    $user = User::factory()->create();
    $headers = m7UserHeaders($user);

    $this->withHeaders($headers)->putJson('/api/v1/platform/profile', [
        'country_code' => 'PK', 'platform' => 'android', 'app_version' => '2.1.0', 'locale' => 'ur',
    ])->assertOk();
    $this->withHeaders($headers)->putJson('/api/v1/platform/profile', ['app_version' => '2.2.0'])->assertOk();

    $profile = UserRegionalProfile::query()->where('user_id', $user->id)->firstOrFail();
    expect($profile->country_code)->toBe('PK')
        ->and($profile->platform)->toBe('android')
        ->and($profile->locale)->toBe('ur')
        ->and($profile->app_version)->toBe('2.2.0');
});

it('app version policies enforce soft and forced update assessments after DevOps reauthentication', function (): void {
    $admin = m7Admin('devops-operator');
    $payload = [
        'minimum_supported_version' => '2.0.0',
        'recommended_version' => '2.5.0',
        'latest_version' => '3.0.0',
        'soft_update_message' => 'A newer Orbit version is recommended.',
        'forced_update_message' => 'Update Orbit to continue.',
    ];

    $this->withHeaders(m7AdminHeaders($admin, false))->putJson('/api/admin/v1/app-versions/android', $payload)->assertStatus(428);
    $this->withHeaders(m7AdminHeaders($admin, true))->putJson('/api/admin/v1/app-versions/android', $payload)->assertOk();

    $this->withHeaders(['Authorization' => '']);

    $this->getJson('/api/v1/platform/config?platform=android&app_version=2.2.0')->assertOk()->assertJsonPath('data.app_version.status', 'soft_update');
    $this->getJson('/api/v1/platform/config?platform=android&app_version=1.9.0')->assertOk()->assertJsonPath('data.app_version.status', 'force_update');
    $this->getJson('/api/v1/platform/config?platform=android&app_version=2.6.0')->assertOk()->assertJsonPath('data.app_version.status', 'supported');
});

it('maintenance activation requires recent DevOps reauthentication', function (): void {
    $admin = m7Admin('devops-operator');
    $headers = m7AdminHeaders($admin, true);
    $id = $this->withHeaders($headers)->postJson('/api/admin/v1/maintenance', [
        'service' => 'global', 'title' => 'Maintenance', 'message' => 'Temporary maintenance window.',
    ])->assertCreated()->json('data.id');

    $this->withHeaders(m7AdminHeaders($admin, false))->postJson('/api/admin/v1/maintenance/'.$id.'/activate', ['reason' => 'Deploy'])
        ->assertStatus(428)->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');
    $this->withHeaders(m7AdminHeaders($admin, true))->postJson('/api/admin/v1/maintenance/'.$id.'/activate', ['reason' => 'Deploy'])
        ->assertOk()->assertJsonPath('data.status', 'active');
});

it('full maintenance blocks ordinary consumer APIs but never intercepts SOS or platform config', function (): void {
    $user = User::factory()->create();
    MaintenanceWindow::query()->create([
        'environment' => app()->environment(), 'service' => 'global', 'status' => 'active', 'read_only' => false,
        'title' => 'Global maintenance', 'message' => 'Orbit is undergoing maintenance.', 'starts_at' => now()->subMinute(),
    ]);
    $headers = m7UserHeaders($user);

    $this->withHeaders($headers)->getJson('/api/v1/circles')->assertStatus(503)->assertJsonPath('code', 'MAINTENANCE_ACTIVE')->assertJsonPath('data.sos_available', true);
    $this->getJson('/api/v1/platform/config')->assertOk()->assertJsonPath('data.maintenance.sos_available', true);
    $this->withHeaders($headers)->postJson('/api/v1/sos/activate', [])->assertUnprocessable();
});

it('read only maintenance preserves reads while blocking ordinary writes', function (): void {
    $user = User::factory()->create();
    MaintenanceWindow::query()->create([
        'environment' => app()->environment(), 'service' => 'global', 'status' => 'active', 'read_only' => true,
        'title' => 'Read only', 'message' => 'Writes are temporarily paused.', 'starts_at' => now()->subMinute(),
    ]);
    $headers = m7UserHeaders($user);

    $this->withHeaders($headers)->getJson('/api/v1/circles')->assertOk();
    $this->withHeaders($headers)->postJson('/api/v1/circles', ['name' => 'Blocked write'])->assertStatus(503)->assertJsonPath('code', 'MAINTENANCE_ACTIVE');
});

it('service specific maintenance takes precedence over a global window for the affected service', function (): void {
    MaintenanceWindow::query()->create([
        'environment' => app()->environment(), 'service' => 'global', 'status' => 'active', 'read_only' => true,
        'title' => 'Global read only', 'message' => 'Global', 'starts_at' => now()->subMinute(),
    ]);
    $specific = MaintenanceWindow::query()->create([
        'environment' => app()->environment(), 'service' => 'messaging', 'status' => 'active', 'read_only' => false,
        'title' => 'Messaging outage', 'message' => 'Messaging unavailable', 'starts_at' => now()->subMinute(),
    ]);

    expect(app(RegionalPlatformService::class)->activeMaintenance(app()->environment(), 'messaging')?->id)->toBe($specific->id);
});

it('expired maintenance windows are completed by the cleanup command', function (): void {
    $window = MaintenanceWindow::query()->create([
        'environment' => app()->environment(), 'service' => 'global', 'status' => 'active', 'read_only' => false,
        'title' => 'Ended', 'message' => 'Ended', 'starts_at' => now()->subHour(), 'ends_at' => now()->subMinute(),
    ]);

    $this->artisan('orbit:maintenance:close-expired')->assertSuccessful();
    expect($window->refresh()->status)->toBe('completed');
});

it('consequential M7 mutations create immutable administrator audit history', function (): void {
    $admin = m7Admin('devops-operator');
    $headers = m7AdminHeaders($admin, true);
    $id = $this->withHeaders($headers)->postJson('/api/admin/v1/maintenance', [
        'title' => 'Audited maintenance', 'message' => 'Audit this maintenance window.',
    ])->assertCreated()->json('data.id');
    $this->withHeaders($headers)->postJson('/api/admin/v1/maintenance/'.$id.'/activate', ['reason' => 'Audited deployment'])->assertOk();

    expect(AdminAuditLog::query()->where('action', 'maintenance.window.created')->exists())->toBeTrue()
        ->and(AdminAuditLog::query()->where('action', 'maintenance.window.activated')->exists())->toBeTrue();
});

it('unknown M7 identifiers return not found without leaking records', function (): void {
    $marketing = m7AdminHeaders(m7Admin());
    $devops = m7AdminHeaders(m7Admin('devops-operator'));

    $this->withHeaders($marketing)->getJson('/api/admin/v1/communications/campaigns/'.Str::uuid())->assertNotFound();
    $this->withHeaders($marketing)->postJson('/api/admin/v1/templates/'.Str::uuid().'/publish')->assertNotFound();
    $this->withHeaders($devops)->postJson('/api/admin/v1/maintenance/'.Str::uuid().'/activate')->assertNotFound();
});
