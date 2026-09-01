<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('identity_device_trusts')) {
            Schema::create('identity_device_trusts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->index();
                $table->string('device_id')->index();
                $table->string('status', 24)->default('pending')->index();
                $table->string('requested_by_device_id')->nullable();
                $table->string('approved_by_device_id')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'device_id']);
            });
        }

        if (! Schema::hasTable('identity_sessions')) {
            Schema::create('identity_sessions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->index();
                $table->string('device_id')->index();
                $table->unsignedBigInteger('access_token_id')->nullable()->index();
                $table->uuid('refresh_family_id')->index();
                $table->string('status', 24)->default('active')->index();
                $table->string('device_key_fingerprint', 64)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('access_expires_at')->nullable();
                $table->timestamp('refresh_expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->string('revoke_reason', 80)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('identity_refresh_tokens')) {
            Schema::create('identity_refresh_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('session_id')->index();
                $table->foreignId('user_id')->index();
                $table->string('device_id')->index();
                $table->uuid('family_id')->index();
                $table->string('token_hash', 64)->unique();
                $table->string('status', 24)->default('active')->index();
                $table->uuid('replaced_by_id')->nullable();
                $table->timestamp('expires_at')->index();
                $table->timestamp('rotated_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('reuse_detected_at')->nullable();
                $table->timestamps();
                $table->index(['family_id', 'status']);
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->foreignId('actor_user_id')->nullable()->index();
                $table->string('action', 100)->index();
                $table->string('target_type', 80)->nullable();
                $table->string('target_id')->nullable();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent_hash', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
                $table->index(['user_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_refresh_tokens');
        Schema::dropIfExists('identity_sessions');
        Schema::dropIfExists('identity_device_trusts');
        Schema::dropIfExists('audit_logs');
    }
};
