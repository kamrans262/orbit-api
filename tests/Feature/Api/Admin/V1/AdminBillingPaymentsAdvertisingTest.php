<?php

declare(strict_types=1);

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdEvent;
use App\Models\AdminAuditLog;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\Advertiser;
use App\Models\BillingPlan;
use App\Models\BillingPlanPrice;
use App\Models\Circle;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\UserContactEvent;
use App\Models\UserSubscription;
use App\Modules\Admin\BillingAdvertising\Services\BillingCatalogService;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Services\AdminRbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(BillingCatalogService::class)->syncDefaults();
});

function m6Admin(string $role = 'finance-manager'): AdminUser
{
    app(AdminRbacService::class)->syncDefaults();
    app(BillingCatalogService::class)->syncDefaults();
    $admin = AdminUser::query()->create(['name' => 'M6 Admin', 'email' => Str::uuid().'@m6.orbit.test', 'password' => 'StrongPassword!123', 'status' => AdminStatus::Active, 'mfa_confirmed_at' => now(), 'activated_at' => now()]);
    $admin->roles()->sync([AdminRole::query()->where('slug', $role)->firstOrFail()->id]);

    return $admin;
}
function m6AdminHeaders(AdminUser $admin, bool $reauth = true): array
{
    app('auth')->forgetGuards();
    $token = $admin->createToken('m6-admin', ['admin'], now()->addHours(2));
    AdminSession::query()->create(['id' => (string) Str::uuid7(), 'admin_user_id' => $admin->id, 'access_token_id' => $token->accessToken->id, 'last_seen_at' => now(), 'idle_expires_at' => now()->addHour(), 'expires_at' => now()->addHours(2), 'reauthenticated_at' => $reauth ? now() : now()->subHour(), 'mfa_verified_at' => now()]);

    return ['Authorization' => 'Bearer '.$token->plainTextToken];
}
function m6UserHeaders(User $user): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('m6-user')->plainTextToken];
}
function m6Price(string $slug = 'plus', int $amount = 999): BillingPlanPrice
{
    app(BillingCatalogService::class)->syncDefaults();
    $plan = BillingPlan::query()->where('slug', $slug)->firstOrFail();

    return BillingPlanPrice::query()->create(['plan_id' => $plan->id, 'billing_interval' => 'monthly', 'currency' => 'USD', 'amount_minor' => $amount, 'provider' => 'manual', 'starts_at' => now()->subDay()]);
}

it('requires admin authentication for billing and advertising admin APIs', function () {
    $this->getJson('/api/admin/v1/billing/plans')->assertUnauthorized();
    $this->getJson('/api/admin/v1/subscriptions')->assertUnauthorized();
    $this->getJson('/api/admin/v1/payments')->assertUnauthorized();
    $this->getJson('/api/admin/v1/advertising/campaigns')->assertUnauthorized();
});
it('requires consumer authentication for subscription and ads', function () {
    $this->getJson('/api/v1/me/subscription')->assertUnauthorized();
    $this->getJson('/api/v1/ads/feed_card')->assertUnauthorized();
});
it('syncs free lite plus without inventing paid prices', function () {
    app(BillingCatalogService::class)->syncDefaults();
    expect(BillingPlan::query()->pluck('slug')->sort()->values()->all())->toBe(['free', 'lite', 'plus'])->and(BillingPlanPrice::query()->count())->toBe(0);
});
it('consumer subscription endpoint lazily creates a free subscription and returns entitlements', function () {
    $u = User::factory()->create();
    $this->withHeaders(m6UserHeaders($u))->getJson('/api/v1/me/subscription')->assertOk()->assertJsonPath('data.plan.slug', 'free')->assertJsonPath('data.entitlements.ads.enabled', true);
    expect(UserSubscription::query()->where('user_id', $u->id)->count())->toBe(1);
});
it('read only can view billing but cannot mutate it', function () {
    $h = m6AdminHeaders(m6Admin('read-only'));
    $this->withHeaders($h)->getJson('/api/admin/v1/billing/plans')->assertOk();
    $this->withHeaders($h)->postJson('/api/admin/v1/billing/plans', ['slug' => 'x', 'name' => 'X'])->assertForbidden();
});
it('finance manager can create a plan and audited price only after recent reauthentication', function () {
    $admin = m6Admin();
    $h = m6AdminHeaders($admin, false);
    $plan = BillingPlan::query()->where('slug', 'plus')->firstOrFail();
    $this->withHeaders($h)->postJson('/api/admin/v1/billing/plans/'.$plan->id.'/prices', ['billing_interval' => 'monthly', 'currency' => 'USD', 'amount_minor' => 999])->assertStatus(428);
    $h = m6AdminHeaders($admin, true);
    $this->withHeaders($h)->postJson('/api/admin/v1/billing/plans/'.$plan->id.'/prices', ['billing_interval' => 'monthly', 'currency' => 'USD', 'amount_minor' => 999])->assertCreated();
});
it('paid plan assignment fails safely until price is configured', function () {
    $u = User::factory()->create();
    $admin = m6Admin();
    $this->withHeaders(m6AdminHeaders($admin))->postJson('/api/admin/v1/subscriptions/users/'.$u->id.'/change-plan', ['plan_slug' => 'plus', 'billing_interval' => 'monthly', 'currency' => 'USD', 'reason' => 'Upgrade requested'])->assertStatus(409)->assertJsonPath('code', 'BILLING_PRICE_NOT_CONFIGURED');
});
it('finance can assign a paid plan and subscription snapshots the charged price', function () {
    $u = User::factory()->create();
    m6Price('plus', 1299);
    $this->withHeaders(m6AdminHeaders(m6Admin()))->postJson('/api/admin/v1/subscriptions/users/'.$u->id.'/change-plan', ['plan_slug' => 'plus', 'billing_interval' => 'monthly', 'currency' => 'USD', 'reason' => 'Upgrade requested'])->assertOk()->assertJsonPath('data.price.amount_minor', 1299);
});
it('later pricing changes do not rewrite historical subscription price', function () {
    $u = User::factory()->create();
    $price = m6Price('plus', 999);
    $h = m6AdminHeaders(m6Admin());
    $r = $this->withHeaders($h)->postJson('/api/admin/v1/subscriptions/users/'.$u->id.'/change-plan', ['plan_slug' => 'plus', 'billing_interval' => 'monthly', 'currency' => 'USD', 'reason' => 'Initial price'])->assertOk();
    $price->forceFill(['amount_minor' => 1499])->save();
    expect(UserSubscription::query()->find($r->json('data.id'))->price_amount_minor)->toBe(999);
});
it('complimentary subscription does not require a configured price', function () {
    $u = User::factory()->create();
    $this->withHeaders(m6AdminHeaders(m6Admin()))->postJson('/api/admin/v1/subscriptions/users/'.$u->id.'/complimentary', ['plan_slug' => 'plus', 'duration_days' => 30, 'reason' => 'Support recovery grant'])->assertCreated()->assertJsonPath('data.complimentary', true);
});
it('subscription extension cancellation and restoration are real lifecycle operations', function () {
    $u = User::factory()->create();
    $h = m6AdminHeaders(m6Admin());
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/subscriptions/users/'.$u->id.'/complimentary', ['plan_slug' => 'plus', 'duration_days' => 30, 'reason' => 'Grant'])->json('data.id');
    $this->withHeaders($h)->postJson('/api/admin/v1/subscriptions/'.$id.'/extend', ['days' => 5, 'reason' => 'Extension'])->assertOk();
    $this->withHeaders($h)->postJson('/api/admin/v1/subscriptions/'.$id.'/cancel', ['immediate' => false, 'reason' => 'User request'])->assertOk()->assertJsonPath('data.status', 'cancel_pending');
    $this->withHeaders($h)->postJson('/api/admin/v1/subscriptions/'.$id.'/restore', ['reason' => 'User reversed cancellation'])->assertOk()->assertJsonPath('data.status', 'active');
});
it('promotion redemption is single use and affects only new subscription snapshot', function () {
    $u = User::factory()->create();
    m6Price('plus', 1000);
    $h = m6AdminHeaders(m6Admin());
    $promo = $this->withHeaders($h)->postJson('/api/admin/v1/billing/promotions', ['code' => 'SAVE20', 'name' => 'Save 20', 'percent_off' => 20])->assertCreated()->json('data.code');
    $this->withHeaders($h)->postJson('/api/admin/v1/subscriptions/users/'.$u->id.'/change-plan', ['plan_slug' => 'plus', 'billing_interval' => 'monthly', 'currency' => 'USD', 'promotion_code' => $promo, 'reason' => 'Promo'])->assertOk()->assertJsonPath('data.price.amount_minor', 800);
    $this->withHeaders($h)->postJson('/api/admin/v1/subscriptions/users/'.$u->id.'/change-plan', ['plan_slug' => 'plus', 'billing_interval' => 'monthly', 'currency' => 'USD', 'promotion_code' => $promo, 'reason' => 'Retry'])->assertStatus(409);
});
it('payment reconciliation strips secret shaped metadata', function () {
    $u = User::factory()->create();
    $h = m6AdminHeaders(m6Admin());
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/payments/reconcile', ['user_id' => $u->id, 'provider' => 'manual', 'provider_transaction_ref' => 'tx-1', 'type' => 'charge', 'amount_minor' => 1000, 'currency' => 'USD', 'status' => 'succeeded', 'reason' => 'Reconcile', 'metadata' => ['card_number' => '4111', 'safe' => 'ok']])->assertCreated()->json('data.id');
    $p = PaymentTransaction::query()->findOrFail($id);
    expect($p->metadata)->toBe(['safe' => 'ok']);
});
it('payment reconciliation is idempotent by provider reference', function () {
    $u = User::factory()->create();
    $h = m6AdminHeaders(m6Admin());
    $payload = ['user_id' => $u->id, 'provider' => 'manual', 'provider_transaction_ref' => 'tx-idem', 'type' => 'charge', 'amount_minor' => 1000, 'currency' => 'USD', 'status' => 'succeeded', 'reason' => 'Reconcile'];
    $this->withHeaders($h)->postJson('/api/admin/v1/payments/reconcile', $payload)->assertCreated();
    $this->withHeaders($h)->postJson('/api/admin/v1/payments/reconcile', $payload)->assertCreated();
    expect(PaymentTransaction::query()->where('provider_transaction_ref', 'tx-idem')->count())->toBe(1);
});
it('refund cannot exceed remaining refundable amount', function () {
    $u = User::factory()->create();
    $p = PaymentTransaction::query()->create(['user_id' => $u->id, 'provider' => 'manual', 'provider_transaction_ref' => 'r1', 'type' => 'charge', 'amount_minor' => 1000, 'currency' => 'USD', 'status' => 'succeeded', 'occurred_at' => now()]);
    $h = m6AdminHeaders(m6Admin());
    $this->withHeaders($h)->postJson('/api/admin/v1/payments/'.$p->id.'/refunds', ['amount_minor' => 1001, 'reason' => 'Too much'])->assertUnprocessable();
});
it('refund approval requires separately sensitive permission and recent reauthentication', function () {
    $u = User::factory()->create();
    $p = PaymentTransaction::query()->create(['user_id' => $u->id, 'provider' => 'manual', 'provider_transaction_ref' => 'r2', 'type' => 'charge', 'amount_minor' => 1000, 'currency' => 'USD', 'status' => 'succeeded', 'occurred_at' => now()]);
    $finance = m6Admin();
    $id = $this->withHeaders(m6AdminHeaders($finance))->postJson('/api/admin/v1/payments/'.$p->id.'/refunds', ['amount_minor' => 500, 'reason' => 'Customer request'])->assertCreated()->json('data.id');
    $this->withHeaders(m6AdminHeaders($finance, false))->postJson('/api/admin/v1/refunds/'.$id.'/decision', ['decision' => 'approve', 'reason' => 'Approved'])->assertStatus(428);
    $this->withHeaders(m6AdminHeaders(m6Admin('super-administrator')))->postJson('/api/admin/v1/refunds/'.$id.'/decision', ['decision' => 'approve', 'reason' => 'Approved'])->assertForbidden();
});
it('manual refund completes immediately and updates payment status', function () {
    $u = User::factory()->create();
    $p = PaymentTransaction::query()->create(['user_id' => $u->id, 'provider' => 'manual', 'provider_transaction_ref' => 'r3', 'type' => 'charge', 'amount_minor' => 1000, 'currency' => 'USD', 'status' => 'succeeded', 'occurred_at' => now()]);
    $h = m6AdminHeaders(m6Admin());
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/payments/'.$p->id.'/refunds', ['amount_minor' => 500, 'reason' => 'Customer request'])->json('data.id');
    $this->withHeaders($h)->postJson('/api/admin/v1/refunds/'.$id.'/decision', ['decision' => 'approve', 'reason' => 'Approved'])->assertOk()->assertJsonPath('data.status', 'succeeded');
    expect($p->refresh()->status)->toBe('partially_refunded');
});
it('external refund remains pending until provider result is reconciled', function () {
    $u = User::factory()->create();
    $p = PaymentTransaction::query()->create(['user_id' => $u->id, 'provider' => 'app_store', 'provider_transaction_ref' => 'r4', 'type' => 'charge', 'amount_minor' => 1000, 'currency' => 'USD', 'status' => 'succeeded', 'occurred_at' => now()]);
    $h = m6AdminHeaders(m6Admin());
    $id = $this->withHeaders($h)->postJson('/api/admin/v1/payments/'.$p->id.'/refunds', ['amount_minor' => 1000, 'reason' => 'Customer request'])->json('data.id');
    $this->withHeaders($h)->postJson('/api/admin/v1/refunds/'.$id.'/decision', ['decision' => 'approve', 'reason' => 'Approved'])->assertAccepted()->assertJsonPath('data.status', 'pending_provider');
    $this->withHeaders($h)->postJson('/api/admin/v1/refunds/'.$id.'/provider-result', ['succeeded' => true, 'provider_result' => 'ok', 'provider_ref' => 'rf-1', 'reason' => 'Provider callback'])->assertOk()->assertJsonPath('data.status', 'succeeded');
    expect($p->refresh()->status)->toBe('refunded');
});
it('revenue summary uses ledger and subscription snapshots', function () {
    $u = User::factory()->create();
    $plan = BillingPlan::query()->where('slug', 'plus')->firstOrFail();
    UserSubscription::query()->create(['user_id' => $u->id, 'plan_id' => $plan->id, 'status' => 'active', 'source' => 'admin', 'provider' => 'manual', 'price_amount_minor' => 1200, 'price_currency' => 'USD', 'billing_interval' => 'monthly', 'started_at' => now()]);
    PaymentTransaction::query()->create(['user_id' => $u->id, 'provider' => 'manual', 'provider_transaction_ref' => 'rev1', 'type' => 'charge', 'amount_minor' => 1200, 'currency' => 'USD', 'status' => 'succeeded', 'occurred_at' => now()]);
    $this->withHeaders(m6AdminHeaders(m6Admin('analyst')))->getJson('/api/admin/v1/revenue/summary')->assertOk()->assertJsonPath('data.mrr_minor', 1200)->assertJsonPath('data.gross_revenue_minor', 1200);
});
it('advertising manager can create advertiser campaign and creative', function () {
    $h = m6AdminHeaders(m6Admin('advertising-manager'));
    $a = $this->withHeaders($h)->postJson('/api/admin/v1/advertising/advertisers', ['name' => 'Orbit Sponsor'])->assertCreated()->json('data.id');
    $c = $this->withHeaders($h)->postJson('/api/admin/v1/advertising/campaigns', ['advertiser_id' => $a, 'name' => 'Feed', 'placement' => 'feed_card', 'status' => 'active'])->assertCreated()->json('data.id');
    $this->withHeaders($h)->postJson('/api/admin/v1/advertising/campaigns/'.$c.'/creatives', ['type' => 'card', 'title' => 'Sponsored update'])->assertCreated();
});
it('free user receives eligible sponsored feed card and paid user does not', function () {
    $adv = Advertiser::query()->create(['name' => 'Sponsor', 'status' => 'active']);
    $campaign = AdCampaign::query()->create(['advertiser_id' => $adv->id, 'name' => 'Feed', 'status' => 'active', 'placement' => 'feed_card', 'priority' => 100]);
    AdCreative::query()->create(['campaign_id' => $campaign->id, 'type' => 'card', 'status' => 'active', 'title' => 'Sponsored']);
    $free = User::factory()->create();
    $this->withHeaders(m6UserHeaders($free))->getJson('/api/v1/ads/feed_card')->assertOk()->assertJsonCount(1, 'data');
    $paid = User::factory()->create();
    $plus = BillingPlan::query()->where('slug', 'plus')->firstOrFail();
    UserSubscription::query()->create(['user_id' => $paid->id, 'plan_id' => $plus->id, 'status' => 'active', 'source' => 'admin', 'provider' => 'manual', 'price_amount_minor' => 1000, 'price_currency' => 'USD', 'billing_interval' => 'monthly', 'started_at' => now()]);
    $this->withHeaders(m6UserHeaders($paid))->getJson('/api/v1/ads/feed_card')->assertOk()->assertJsonCount(0, 'data');
});
it('active SOS suppresses all ad delivery server side', function () {
    $adv = Advertiser::query()->create(['name' => 'Sponsor', 'status' => 'active']);
    $c = AdCampaign::query()->create(['advertiser_id' => $adv->id, 'name' => 'Feed', 'status' => 'active', 'placement' => 'feed_card', 'priority' => 100]);
    AdCreative::query()->create(['campaign_id' => $c->id, 'type' => 'card', 'status' => 'active', 'title' => 'Sponsored']);
    $u = User::factory()->create();
    $circle = Circle::query()->create(['created_by' => $u->id, 'name' => 'SOS Ad Suppression', 'type' => 'standard']);
    DB::table('sos_events')->insert(['id' => (string) Str::uuid7(), 'user_id' => $u->id, 'circle_id' => $circle->id, 'status' => 'active', 'escalation_stage' => 0, 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $this->withHeaders(m6UserHeaders($u))->getJson('/api/v1/ads/feed_card')->assertOk()->assertJsonCount(0, 'data');
});
it('campaign targeting respects plan country and platform', function () {
    $adv = Advertiser::query()->create(['name' => 'Sponsor', 'status' => 'active']);
    $c = AdCampaign::query()->create(['advertiser_id' => $adv->id, 'name' => 'Targeted', 'status' => 'active', 'placement' => 'feed_card', 'targeting' => ['plans' => ['free'], 'countries' => ['PK'], 'platforms' => ['android']], 'priority' => 100]);
    AdCreative::query()->create(['campaign_id' => $c->id, 'type' => 'card', 'status' => 'active', 'title' => 'Targeted']);
    $u = User::factory()->create();
    $this->withHeaders(m6UserHeaders($u))->getJson('/api/v1/ads/feed_card?country=US&platform=android')->assertOk()->assertJsonCount(0, 'data');
    $this->withHeaders(m6UserHeaders($u))->getJson('/api/v1/ads/feed_card?country=PK&platform=android')->assertOk()->assertJsonCount(1, 'data');
});
it('ad event client id is idempotent and hide suppresses later delivery', function () {
    $adv = Advertiser::query()->create(['name' => 'Sponsor', 'status' => 'active']);
    $c = AdCampaign::query()->create(['advertiser_id' => $adv->id, 'name' => 'Feed', 'status' => 'active', 'placement' => 'feed_card', 'priority' => 100]);
    $cr = AdCreative::query()->create(['campaign_id' => $c->id, 'type' => 'card', 'status' => 'active', 'title' => 'Sponsored']);
    $u = User::factory()->create();
    $h = m6UserHeaders($u);
    $payload = ['event_type' => 'impression', 'creative_id' => $cr->id, 'client_event_id' => 'evt-1'];
    $this->withHeaders($h)->postJson('/api/v1/ads/'.$c->id.'/events', $payload)->assertAccepted();
    $this->withHeaders($h)->postJson('/api/v1/ads/'.$c->id.'/events', $payload)->assertAccepted();
    expect(AdEvent::query()->where('client_event_id', 'evt-1')->count())->toBe(1);
    $this->withHeaders($h)->postJson('/api/v1/ads/'.$c->id.'/events', ['event_type' => 'hide', 'creative_id' => $cr->id, 'client_event_id' => 'evt-hide'])->assertAccepted();
    $this->withHeaders($h)->getJson('/api/v1/ads/feed_card')->assertOk()->assertJsonCount(0, 'data');
});
it('impression cap is enforced per user', function () {
    $adv = Advertiser::query()->create(['name' => 'Sponsor', 'status' => 'active']);
    $c = AdCampaign::query()->create(['advertiser_id' => $adv->id, 'name' => 'Feed', 'status' => 'active', 'placement' => 'feed_card', 'impression_cap_per_user' => 1, 'priority' => 100]);
    $cr = AdCreative::query()->create(['campaign_id' => $c->id, 'type' => 'card', 'status' => 'active', 'title' => 'Sponsored']);
    $u = User::factory()->create();
    $h = m6UserHeaders($u);
    $this->withHeaders($h)->postJson('/api/v1/ads/'.$c->id.'/events', ['event_type' => 'impression', 'creative_id' => $cr->id, 'client_event_id' => 'cap1'])->assertAccepted();
    $this->withHeaders($h)->getJson('/api/v1/ads/feed_card')->assertOk()->assertJsonCount(0, 'data');
});
it('finance and advertising permissions are separated', function () {
    $finance = m6Admin('finance-manager');
    $ad = m6Admin('advertising-manager');
    $this->withHeaders(m6AdminHeaders($finance))->postJson('/api/admin/v1/advertising/advertisers', ['name' => 'No'])->assertForbidden();
    $this->withHeaders(m6AdminHeaders($ad))->getJson('/api/admin/v1/revenue/summary')->assertForbidden();
});
it('default super admin does not silently receive refund approval', function () {
    $admin = m6Admin('super-administrator');
    expect($admin->hasPermission('refunds.approve'))->toBeFalse();
});
it('billing mutations produce admin audit records', function () {
    $h = m6AdminHeaders(m6Admin());
    $this->withHeaders($h)->postJson('/api/admin/v1/billing/plans', ['slug' => 'enterprise-test', 'name' => 'Enterprise Test'])->assertCreated();
    expect(AdminAuditLog::query()->where('action', 'billing.plan.created')->exists())->toBeTrue();
});
it('subscription changes create safe user contact history', function () {
    $u = User::factory()->create();
    $this->withHeaders(m6AdminHeaders(m6Admin()))->postJson('/api/admin/v1/subscriptions/users/'.$u->id.'/complimentary', ['plan_slug' => 'plus', 'duration_days' => 30, 'reason' => 'Grant'])->assertCreated();
    expect(UserContactEvent::query()->where('user_id', $u->id)->where('kind', 'subscription.plan_changed')->exists())->toBeTrue();
});
it('subscription expiry command marks due records expired', function () {
    $u = User::factory()->create();
    $plan = BillingPlan::query()->where('slug', 'plus')->firstOrFail();
    $s = UserSubscription::query()->create(['user_id' => $u->id, 'plan_id' => $plan->id, 'status' => 'active', 'source' => 'admin', 'provider' => 'manual', 'price_amount_minor' => 0, 'price_currency' => 'USD', 'billing_interval' => 'monthly', 'complimentary' => true, 'started_at' => now()->subMonth(), 'ends_at' => now()->subMinute()]);
    $this->artisan('orbit:billing:expire-subscriptions')->assertSuccessful();
    expect($s->refresh()->status)->toBe('expired');
});
it('unknown billing and advertising identifiers do not leak records', function () {
    $h = m6AdminHeaders(m6Admin());
    $this->withHeaders($h)->patchJson('/api/admin/v1/billing/plans/'.Str::uuid(),['name' => 'x'])->assertNotFound();
    $ah = m6AdminHeaders(m6Admin('advertising-manager'));
    $this->withHeaders($ah)->patchJson('/api/admin/v1/advertising/campaigns/'.Str::uuid(),['status' => 'paused'])->assertNotFound();
});
