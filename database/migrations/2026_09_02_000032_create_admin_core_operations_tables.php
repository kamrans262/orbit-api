<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_controls', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('suspended_until')->nullable()->index();
            $table->string('suspension_reason', 500)->nullable();
            $table->json('feature_restrictions')->nullable();
            $table->unsignedSmallInteger('rate_limit_per_minute')->nullable();
            $table->boolean('require_reverification')->default(false)->index();
            $table->string('risk_level', 24)->default('normal')->index();
            $table->string('warning', 500)->nullable();
            $table->timestamp('trust_safety_escalated_at')->nullable()->index();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_device_controls', function (Blueprint $table): void {
            $table->uuid('device_id')->primary();
            $table->boolean('suspicious')->default(false)->index();
            $table->boolean('require_verification')->default(false)->index();
            $table->boolean('enforcement_revoked')->default(false)->index();
            $table->string('reason', 500)->nullable();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
        });

        Schema::create('admin_circle_controls', function (Blueprint $table): void {
            $table->uuid('circle_id')->primary();
            $table->string('status', 24)->default('normal')->index();
            $table->json('feature_restrictions')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('frozen_at')->nullable()->index();
            $table->timestamp('removed_at')->nullable()->index();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('circle_id')->references('id')->on('circles')->cascadeOnDelete();
        });

        Schema::create('admin_record_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('target_type', 40)->index();
            $table->string('target_id', 160)->index();
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id', 'created_at']);
        });

        Schema::create('admin_record_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('target_type', 40)->index();
            $table->string('target_id', 160)->index();
            $table->string('tag', 80);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['target_type', 'target_id', 'tag'], 'admin_record_tags_unique');
            $table->index(['target_type', 'target_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_record_tags');
        Schema::dropIfExists('admin_record_notes');
        Schema::dropIfExists('admin_circle_controls');
        Schema::dropIfExists('admin_device_controls');
        Schema::dropIfExists('admin_user_controls');
    }
};
