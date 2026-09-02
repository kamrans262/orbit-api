<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('moderation_reports')) {
            Schema::create('moderation_reports', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('client_report_id')->nullable();
                $table->unsignedBigInteger('reporter_user_id')->nullable();
                $table->string('target_type', 24);
                $table->string('target_id', 64);
                $table->unsignedBigInteger('target_user_id')->nullable();
                $table->string('source', 24)->default('consumer');
                $table->string('source_report_id', 64)->nullable();
                $table->string('reason', 40);
                $table->text('details')->nullable();
                $table->json('evidence')->nullable();
                $table->json('target_snapshot')->nullable();
                $table->string('status', 24)->default('new');
                $table->string('priority', 16)->default('normal');
                $table->unsignedTinyInteger('risk_score')->default(0);
                $table->foreignId('assigned_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->timestamp('triaged_at')->nullable();
                $table->timestamp('review_started_at')->nullable();
                $table->timestamp('actioned_at')->nullable();
                $table->timestamp('escalated_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->foreign('reporter_user_id', 'mod_reports_reporter_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('target_user_id', 'mod_reports_target_user_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->unique(['reporter_user_id', 'client_report_id'], 'mod_reports_client_uidx');
                $table->index(['status', 'priority', 'created_at'], 'mod_reports_queue_idx');
                $table->index(['target_type', 'target_id'], 'mod_reports_target_idx');
                $table->index(['assigned_admin_id', 'status'], 'mod_reports_assign_idx');
                $table->index(['target_user_id', 'created_at'], 'mod_reports_user_time_idx');
                $table->unique(['source', 'source_report_id'], 'mod_reports_source_uidx');
            });
        }

        if (! Schema::hasTable('moderation_case_notes')) {
            Schema::create('moderation_case_notes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('report_id');
                $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->text('note');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('report_id', 'mod_notes_report_fk')
                    ->references('id')->on('moderation_reports')->cascadeOnDelete();
                $table->index(['report_id', 'created_at'], 'mod_notes_report_time_idx');
            });
        }

        if (! Schema::hasTable('moderation_enforcements')) {
            Schema::create('moderation_enforcements', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('report_id')->nullable();
                $table->string('target_type', 24);
                $table->string('target_id', 64);
                $table->string('action', 40);
                $table->json('parameters')->nullable();
                $table->string('reason', 500);
                $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->string('status', 24)->default('applied');
                $table->timestamp('applied_at');
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('reversed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->string('reversal_reason', 500)->nullable();
                $table->timestamps();

                $table->foreign('report_id', 'mod_enforce_report_fk')
                    ->references('id')->on('moderation_reports')->nullOnDelete();
                $table->index(['target_type', 'target_id', 'applied_at'], 'mod_enforce_target_idx');
                $table->index(['report_id', 'applied_at'], 'mod_enforce_report_idx');
            });
        }

        if (! Schema::hasTable('moderation_appeals')) {
            Schema::create('moderation_appeals', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('enforcement_id')->unique('mod_appeals_enforce_uidx');
                $table->unsignedBigInteger('user_id');
                $table->text('explanation');
                $table->string('status', 24)->default('submitted');
                $table->foreignId('assigned_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->string('outcome', 24)->nullable();
                $table->string('decision_reason', 500)->nullable();
                $table->json('review_metadata')->nullable();
                $table->foreignId('reviewer_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->boolean('requires_second_review')->default(false);
                $table->foreignId('second_reviewer_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->timestamp('submitted_at');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('second_reviewed_at')->nullable();
                $table->timestamps();

                $table->foreign('enforcement_id', 'mod_appeals_enforce_fk')
                    ->references('id')->on('moderation_enforcements')->cascadeOnDelete();
                $table->foreign('user_id', 'mod_appeals_user_fk')
                    ->references('id')->on('users')->cascadeOnDelete();
                $table->index(['status', 'submitted_at'], 'mod_appeals_queue_idx');
                $table->index(['user_id', 'submitted_at'], 'mod_appeals_user_time_idx');
            });
        }

        if (! Schema::hasTable('admin_risk_profiles')) {
            Schema::create('admin_risk_profiles', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->primary();
                $table->unsignedTinyInteger('score')->default(0);
                $table->string('level', 16)->default('normal');
                $table->json('triggered_rules')->nullable();
                $table->text('analyst_notes')->nullable();
                $table->timestamp('last_evaluated_at')->nullable();
                $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('user_id', 'risk_profiles_user_fk')
                    ->references('id')->on('users')->cascadeOnDelete();
                $table->index(['level', 'score'], 'risk_profiles_level_idx');
            });
        }

        if (! Schema::hasTable('admin_risk_signals')) {
            Schema::create('admin_risk_signals', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->string('type', 40);
                $table->string('severity', 16);
                $table->string('source', 32);
                $table->string('source_id', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->string('resolution_note', 500)->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'risk_signals_user_fk')
                    ->references('id')->on('users')->cascadeOnDelete();
                $table->index(['user_id', 'resolved_at', 'occurred_at'], 'risk_signals_user_open_idx');
                $table->index(['type', 'severity', 'occurred_at'], 'risk_signals_type_idx');
                $table->index(['source', 'source_id'], 'risk_signals_source_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_risk_signals');
        Schema::dropIfExists('admin_risk_profiles');
        Schema::dropIfExists('moderation_appeals');
        Schema::dropIfExists('moderation_enforcements');
        Schema::dropIfExists('moderation_case_notes');
        Schema::dropIfExists('moderation_reports');
    }
};
