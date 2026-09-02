<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('channel', 24)->index();
            $table->string('category', 40)->default('general')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->json('variables')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('published_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('communication_template_translations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->string('locale', 12);
            $table->string('status', 24)->default('draft')->index();
            $table->string('subject', 180)->nullable();
            $table->string('title', 180)->nullable();
            $table->text('body');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->foreign('template_id')->references('id')->on('communication_templates')->cascadeOnDelete();
            $table->unique(['template_id', 'locale'], 'comm_template_locale_unique');
        });

        Schema::create('communication_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('channel', 24)->index();
            $table->string('category', 40)->default('product')->index();
            $table->string('priority', 24)->default('normal')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->uuid('template_id')->nullable();
            $table->string('locale', 12)->default('en');
            $table->string('subject', 180)->nullable();
            $table->string('title', 180);
            $table->text('body');
            $table->string('deep_link', 500)->nullable();
            $table->json('audience');
            $table->boolean('is_emergency')->default(false)->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('stats')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->foreign('template_id')->references('id')->on('communication_templates')->nullOnDelete();
            $table->index(['status', 'scheduled_at'], 'comm_campaign_schedule_idx');
        });

        Schema::create('communication_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('status', 32)->default('queued')->index();
            $table->string('provider', 32)->nullable();
            $table->string('provider_reference', 190)->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->foreign('campaign_id')->references('id')->on('communication_campaigns')->cascadeOnDelete();
            $table->unique(['campaign_id', 'user_id', 'channel'], 'comm_delivery_dedupe');
            $table->index(['campaign_id', 'status'], 'comm_delivery_status_idx');
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 40)->index();
            $table->string('status', 24)->default('draft')->index();
            $table->string('priority', 24)->default('normal')->index();
            $table->boolean('dismissible')->default(true);
            $table->string('deep_link', 500)->nullable();
            $table->json('audience');
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('published_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcement_translations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('announcement_id');
            $table->string('locale', 12);
            $table->string('status', 24)->default('draft')->index();
            $table->string('title', 180);
            $table->text('body');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
            $table->unique(['announcement_id', 'locale'], 'announcement_locale_unique');
        });

        Schema::create('content_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 40)->index();
            $table->string('slug', 120)->unique();
            $table->string('status', 24)->default('draft')->index();
            $table->json('regions')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('published_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('content_translations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('content_item_id');
            $table->string('locale', 12);
            $table->string('status', 24)->default('draft')->index();
            $table->string('title', 180);
            $table->longText('body');
            $table->json('metadata')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->foreign('content_item_id')->references('id')->on('content_items')->cascadeOnDelete();
            $table->unique(['content_item_id', 'locale'], 'content_translation_locale_unique');
        });

        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('document_type', 40)->index();
            $table->string('version', 40);
            $table->string('status', 24)->default('draft')->index();
            $table->json('regions')->nullable();
            $table->boolean('requires_reacceptance')->default(false)->index();
            $table->timestamp('effective_at')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('published_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_type', 'version'], 'legal_type_version_unique');
        });

        Schema::create('legal_document_translations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('legal_document_id');
            $table->string('locale', 12);
            $table->string('status', 24)->default('draft')->index();
            $table->string('title', 180);
            $table->longText('body');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->foreign('legal_document_id')->references('id')->on('legal_documents')->cascadeOnDelete();
            $table->unique(['legal_document_id', 'locale'], 'legal_translation_locale_unique');
        });

        Schema::create('legal_acceptances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('legal_document_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at');
            $table->string('source', 40)->default('consumer');
            $table->timestamps();
            $table->foreign('legal_document_id')->references('id')->on('legal_documents')->cascadeOnDelete();
            $table->unique(['legal_document_id', 'user_id'], 'legal_acceptance_unique');
        });

        Schema::create('regional_configurations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('country_code', 2)->unique();
            $table->string('status', 24)->default('active')->index();
            $table->json('feature_availability')->nullable();
            $table->json('subscription_availability')->nullable();
            $table->json('pricing')->nullable();
            $table->json('legal_disclosures')->nullable();
            $table->boolean('sms_available')->default(false);
            $table->json('emergency_information')->nullable();
            $table->json('consent_requirements')->nullable();
            $table->json('retention_rules')->nullable();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('user_regional_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('platform', 20)->nullable()->index();
            $table->string('app_version', 50)->nullable()->index();
            $table->string('locale', 12)->nullable();
            $table->timestamps();
        });

        Schema::create('app_version_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('platform', 20);
            $table->string('environment', 24)->default('production');
            $table->string('minimum_supported_version', 50)->nullable();
            $table->string('recommended_version', 50)->nullable();
            $table->string('latest_version', 50)->nullable();
            $table->string('update_url', 500)->nullable();
            $table->text('soft_update_message')->nullable();
            $table->text('forced_update_message')->nullable();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['platform', 'environment'], 'app_version_policy_unique');
        });

        Schema::create('maintenance_windows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('environment', 24)->default('production')->index();
            $table->string('service', 40)->default('global')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('read_only')->default(false);
            $table->string('title', 180);
            $table->text('message');
            $table->text('expected_restoration')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('activated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['environment', 'status', 'starts_at'], 'maintenance_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');
        Schema::dropIfExists('app_version_policies');
        Schema::dropIfExists('user_regional_profiles');
        Schema::dropIfExists('regional_configurations');
        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('legal_document_translations');
        Schema::dropIfExists('legal_documents');
        Schema::dropIfExists('content_translations');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('announcement_translations');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('communication_deliveries');
        Schema::dropIfExists('communication_campaigns');
        Schema::dropIfExists('communication_template_translations');
        Schema::dropIfExists('communication_templates');
    }
};
