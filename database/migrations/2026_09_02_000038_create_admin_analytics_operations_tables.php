<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_saved_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->json('metrics');
            $table->json('filters')->nullable();
            $table->string('group_by', 60)->nullable();
            $table->string('comparison', 40)->nullable();
            $table->boolean('team_shared')->default(false)->index();
            $table->string('schedule', 24)->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->index(['admin_user_id', 'created_at'], 'saved_reports_admin_created_idx');
        });

        Schema::create('admin_report_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('saved_report_id')->nullable();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('format', 12)->default('csv');
            $table->string('status', 24)->default('ready')->index();
            $table->string('storage_path', 500)->nullable();
            $table->unsignedBigInteger('row_count')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
            $table->foreign('saved_report_id')->references('id')->on('admin_saved_reports')->nullOnDelete();
        });

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 120)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('environment', 24)->default('production')->index();
            $table->string('status', 24)->default('disabled')->index();
            $table->boolean('default_enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percentage')->default(0);
            $table->json('targeting')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('removal_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('owner_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('remote_config_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 160);
            $table->string('environment', 24)->default('production');
            $table->string('status', 24)->default('active')->index();
            $table->boolean('critical')->default(false)->index();
            $table->json('value');
            $table->text('description')->nullable();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['key', 'environment'], 'remote_config_key_env_unique');
        });

        Schema::create('api_request_metrics', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('request_id', 64)->nullable()->index();
            $table->string('method', 12);
            $table->string('route', 240)->index();
            $table->unsignedSmallInteger('status_code')->index();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('is_admin')->default(false)->index();
            $table->timestamp('occurred_at')->index();
        });

        Schema::create('websocket_metric_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('environment', 24)->default('production')->index();
            $table->unsignedInteger('connections')->default(0);
            $table->unsignedInteger('subscriptions')->default(0);
            $table->unsignedInteger('connect_rate')->default(0);
            $table->unsignedInteger('disconnect_rate')->default(0);
            $table->unsignedInteger('reconnect_rate')->default(0);
            $table->unsignedInteger('fanout_lag_ms')->default(0);
            $table->json('regions')->nullable();
            $table->timestamp('captured_at')->index();
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('system_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 180);
            $table->string('service', 60)->index();
            $table->string('severity', 24)->index();
            $table->string('status', 24)->default('open')->index();
            $table->text('impact')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('started_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->text('resolution')->nullable();
            $table->string('external_reference', 500)->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('system_incident_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('incident_id');
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();
            $table->foreign('incident_id')->references('id')->on('system_incidents')->cascadeOnDelete();
            $table->index(['incident_id', 'created_at'], 'incident_notes_incident_created_idx');
        });

        Schema::create('integration_statuses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 80);
            $table->string('service', 60);
            $table->string('environment', 24)->default('production');
            $table->boolean('enabled')->default(false);
            $table->string('health', 24)->default('unknown')->index();
            $table->json('public_config')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider', 'service', 'environment'], 'integration_provider_service_env_unique');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 80)->index();
            $table->string('event_type', 120)->index();
            $table->string('provider_delivery_ref', 190)->nullable()->index();
            $table->string('endpoint_host', 190)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->char('payload_hash', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_delivery_at')->nullable()->index();
            $table->timestamp('retry_requested_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admin_queue_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('failed_job_uuid', 190)->index();
            $table->string('action', 24);
            $table->string('status', 24)->default('requested')->index();
            $table->text('reason');
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->text('result_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_operational_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 80)->index();
            $table->string('severity', 24)->index();
            $table->string('status', 24)->default('open')->index();
            $table->string('resource_type', 80)->nullable();
            $table->string('resource_id', 190)->nullable();
            $table->string('title', 180);
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'severity', 'created_at'], 'operational_alert_status_severity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_operational_alerts');
        Schema::dropIfExists('admin_queue_actions');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('integration_statuses');
        Schema::dropIfExists('system_incident_notes');
        Schema::dropIfExists('system_incidents');
        Schema::dropIfExists('websocket_metric_snapshots');
        Schema::dropIfExists('api_request_metrics');
        Schema::dropIfExists('remote_config_entries');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('admin_report_exports');
        Schema::dropIfExists('admin_saved_reports');
    }
};
