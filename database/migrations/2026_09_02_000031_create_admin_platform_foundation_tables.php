<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->nullable();
            $table->string('email', 255)->unique();
            $table->string('password')->nullable();
            $table->string('status', 24)->default('invited')->index();
            $table->text('totp_secret')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable()->index();
            $table->timestamp('access_expires_at')->nullable()->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by_admin_id')->references('id')->on('admin_users')->nullOnDelete();
        });

        Schema::create('admin_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by_admin_id')->references('id')->on('admin_users')->nullOnDelete();
        });

        Schema::create('admin_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_sensitive')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('admin_role_permissions', function (Blueprint $table): void {
            $table->uuid('admin_role_id');
            $table->unsignedBigInteger('admin_permission_id');
            $table->timestamps();

            $table->primary(['admin_role_id', 'admin_permission_id']);
            $table->foreign('admin_role_id')->references('id')->on('admin_roles')->cascadeOnDelete();
            $table->foreign('admin_permission_id')->references('id')->on('admin_permissions')->cascadeOnDelete();
        });

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('admin_user_id');
            $table->uuid('admin_role_id');
            $table->timestamps();

            $table->primary(['admin_user_id', 'admin_role_id']);
            $table->foreign('admin_user_id')->references('id')->on('admin_users')->cascadeOnDelete();
            $table->foreign('admin_role_id')->references('id')->on('admin_roles')->cascadeOnDelete();
        });

        Schema::create('admin_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('admin_user_id');
            $table->unsignedBigInteger('invited_by_admin_id')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('admin_users')->cascadeOnDelete();
            $table->foreign('invited_by_admin_id')->references('id')->on('admin_users')->nullOnDelete();
            $table->index(['admin_user_id', 'accepted_at', 'expires_at']);
        });

        Schema::create('admin_mfa_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('admin_user_id');
            $table->string('purpose', 24)->index();
            $table->char('token_hash', 64)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('admin_users')->cascadeOnDelete();
        });

        Schema::create('admin_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->char('code_hash', 64)->unique();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('admin_users')->cascadeOnDelete();
            $table->index(['admin_user_id', 'used_at']);
        });

        Schema::create('admin_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('admin_user_id');
            $table->unsignedBigInteger('access_token_id')->nullable()->index();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('idle_expires_at')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('reauthenticated_at')->nullable();
            $table->timestamp('mfa_verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revoke_reason', 120)->nullable();
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('admin_users')->cascadeOnDelete();
            $table->index(['admin_user_id', 'revoked_at', 'expires_at']);
        });

        Schema::create('admin_login_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->char('email_hash', 64)->nullable()->index();
            $table->string('event_type', 40)->index();
            $table->boolean('success')->default(false)->index();
            $table->boolean('suspicious')->default(false)->index();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->char('user_agent_hash', 64)->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('admin_users')->nullOnDelete();
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->uuid('admin_session_id')->nullable()->index();
            $table->string('action', 140)->index();
            $table->string('target_type', 100)->nullable()->index();
            $table->string('target_id', 160)->nullable()->index();
            $table->string('result', 24)->default('success')->index();
            $table->string('reason', 500)->nullable();
            $table->string('request_id', 80)->nullable()->index();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->char('user_agent_hash', 64)->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();

            $table->foreign('admin_user_id')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('admin_session_id')->references('id')->on('admin_sessions')->nullOnDelete();
            $table->index(['action', 'occurred_at']);
            $table->index(['admin_user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('admin_login_events');
        Schema::dropIfExists('admin_sessions');
        Schema::dropIfExists('admin_recovery_codes');
        Schema::dropIfExists('admin_mfa_challenges');
        Schema::dropIfExists('admin_invitations');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('admin_role_permissions');
        Schema::dropIfExists('admin_permissions');
        Schema::dropIfExists('admin_roles');
        Schema::dropIfExists('admin_users');
    }
};
