<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_saved_views')) {
            Schema::create('admin_saved_views', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('module', 60)->index();
                $table->string('scope', 16)->default('personal')->index();
                $table->json('filters')->nullable();
                $table->json('columns')->nullable();
                $table->json('sort')->nullable();
                $table->timestamps();

                $table->index(['admin_user_id', 'module'], 'saved_views_admin_module_idx');
                $table->index(['scope', 'module'], 'saved_views_scope_module_idx');
            });
        }

        if (! Schema::hasTable('admin_dashboard_preferences')) {
            Schema::create('admin_dashboard_preferences', function (Blueprint $table): void {
                $table->foreignId('admin_user_id')->primary()->constrained('admin_users')->cascadeOnDelete();
                $table->json('layout')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_ip_policies')) {
            Schema::create('admin_ip_policies', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
                $table->string('cidr', 64);
                $table->string('description', 200)->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['admin_user_id', 'cidr'], 'admin_ip_policy_user_cidr_uidx');
                $table->index(['admin_user_id', 'enabled'], 'admin_ip_policy_user_enabled_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_ip_policies');
        Schema::dropIfExists('admin_dashboard_preferences');
        Schema::dropIfExists('admin_saved_views');
    }
};
