<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 40)->unique('bill_plan_slug_uq');
            $table->string('name', 80);
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedSmallInteger('rank')->default(0);
            $table->timestamps();
        });

        Schema::create('billing_plan_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('billing_interval', 16);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('provider', 32)->default('manual');
            $table->string('provider_price_ref', 190)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->foreign('plan_id', 'bill_price_plan_fk')->references('id')->on('billing_plans')->cascadeOnDelete();
            $table->index(['plan_id', 'billing_interval', 'currency'], 'bill_price_lookup_idx');
            $table->index(['starts_at', 'ends_at'], 'bill_price_window_idx');
        });

        Schema::create('billing_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique('bill_ent_slug_uq');
            $table->string('name', 100);
            $table->string('value_type', 16)->default('boolean');
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('billing_plan_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->uuid('entitlement_id');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->foreign('plan_id', 'bill_plan_ent_plan_fk')->references('id')->on('billing_plans')->cascadeOnDelete();
            $table->foreign('entitlement_id', 'bill_plan_ent_ent_fk')->references('id')->on('billing_entitlements')->cascadeOnDelete();
            $table->unique(['plan_id', 'entitlement_id'], 'bill_plan_ent_uq');
        });

        Schema::create('billing_promotions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique('bill_promo_code_uq');
            $table->string('name', 120);
            $table->uuid('plan_id')->nullable();
            $table->unsignedTinyInteger('percent_off')->nullable();
            $table->unsignedBigInteger('amount_off_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->foreign('plan_id', 'bill_promo_plan_fk')->references('id')->on('billing_plans')->nullOnDelete();
            $table->index(['status', 'starts_at', 'ends_at'], 'bill_promo_window_idx');
        });

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->uuid('plan_id');
            $table->string('status', 24)->default('active')->index();
            $table->string('source', 24)->default('admin');
            $table->string('provider', 32)->default('manual');
            $table->string('provider_subscription_ref', 190)->nullable();
            $table->unsignedBigInteger('price_amount_minor')->default(0);
            $table->char('price_currency', 3)->default('USD');
            $table->string('billing_interval', 16)->default('monthly');
            $table->boolean('complimentary')->default(false);
            $table->uuid('promotion_id')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id', 'user_sub_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('plan_id', 'user_sub_plan_fk')->references('id')->on('billing_plans')->restrictOnDelete();
            $table->foreign('promotion_id', 'user_sub_promo_fk')->references('id')->on('billing_promotions')->nullOnDelete();
            $table->foreign('created_by_admin_id', 'user_sub_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->index(['user_id', 'status'], 'user_sub_user_status_idx');
            $table->index(['plan_id', 'status'], 'user_sub_plan_status_idx');
            $table->index(['current_period_end', 'status'], 'user_sub_period_status_idx');
        });

        Schema::create('billing_promotion_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->unsignedBigInteger('user_id');
            $table->uuid('subscription_id');
            $table->timestamp('redeemed_at');
            $table->foreign('promotion_id', 'bill_red_promo_fk')->references('id')->on('billing_promotions')->cascadeOnDelete();
            $table->foreign('user_id', 'bill_red_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('subscription_id', 'bill_red_sub_fk')->references('id')->on('user_subscriptions')->cascadeOnDelete();
            $table->unique(['promotion_id', 'user_id'], 'bill_red_user_promo_uq');
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->uuid('subscription_id')->nullable();
            $table->string('provider', 32);
            $table->string('provider_transaction_ref', 190)->nullable();
            $table->string('type', 24)->default('charge');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->string('failure_code', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->foreign('user_id', 'pay_tx_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('subscription_id', 'pay_tx_sub_fk')->references('id')->on('user_subscriptions')->nullOnDelete();
            $table->unique(['provider', 'provider_transaction_ref'], 'pay_tx_provider_ref_uq');
            $table->index(['user_id', 'occurred_at'], 'pay_tx_user_time_idx');
            $table->index(['status', 'occurred_at'], 'pay_tx_status_time_idx');
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('payment_transaction_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->default('requested')->index();
            $table->string('reason', 500);
            $table->text('internal_note')->nullable();
            $table->string('provider_ref', 190)->nullable();
            $table->string('provider_result', 500)->nullable();
            $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->unsignedBigInteger('decided_by_admin_id')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('payment_transaction_id', 'refund_tx_fk')->references('id')->on('payment_transactions')->cascadeOnDelete();
            $table->foreign('user_id', 'refund_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('requested_by_admin_id', 'refund_req_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('decided_by_admin_id', 'refund_dec_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->index(['payment_transaction_id', 'status'], 'refund_tx_status_idx');
            $table->index(['user_id', 'requested_at'], 'refund_user_time_idx');
        });

        Schema::create('advertisers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('status', 20)->default('active')->index();
            $table->string('external_ref', 120)->nullable()->index();
            $table->string('contact_email', 190)->nullable();
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('advertiser_id');
            $table->string('name', 140);
            $table->string('status', 20)->default('draft')->index();
            $table->string('placement', 24)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->json('targeting')->nullable();
            $table->unsignedInteger('impression_cap_per_user')->nullable();
            $table->unsignedBigInteger('budget_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
            $table->foreign('advertiser_id', 'ad_campaign_adv_fk')->references('id')->on('advertisers')->cascadeOnDelete();
            $table->foreign('created_by_admin_id', 'ad_campaign_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->index(['status', 'placement', 'priority'], 'ad_campaign_delivery_idx');
        });

        Schema::create('ad_creatives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->string('type', 24);
            $table->string('status', 20)->default('active')->index();
            $table->string('title', 120);
            $table->string('body', 500)->nullable();
            $table->string('media_ref', 255)->nullable();
            $table->string('deep_link', 500)->nullable();
            $table->string('cta', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('campaign_id', 'ad_creative_campaign_fk')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->index(['campaign_id', 'status'], 'ad_creative_campaign_idx');
        });

        Schema::create('ad_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->uuid('creative_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('event_type', 16);
            $table->string('client_event_id', 100)->nullable()->unique('ad_event_client_uq');
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->foreign('campaign_id', 'ad_event_campaign_fk')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->foreign('creative_id', 'ad_event_creative_fk')->references('id')->on('ad_creatives')->nullOnDelete();
            $table->foreign('user_id', 'ad_event_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['campaign_id', 'event_type', 'occurred_at'], 'ad_event_campaign_type_idx');
            $table->index(['user_id', 'campaign_id', 'event_type'], 'ad_event_user_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
        Schema::dropIfExists('ad_creatives');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('advertisers');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('billing_promotion_redemptions');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('billing_promotions');
        Schema::dropIfExists('billing_plan_entitlements');
        Schema::dropIfExists('billing_entitlements');
        Schema::dropIfExists('billing_plan_prices');
        Schema::dropIfExists('billing_plans');
    }
};
