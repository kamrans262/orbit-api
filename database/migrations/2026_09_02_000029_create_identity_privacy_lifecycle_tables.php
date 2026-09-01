<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_export_requests')) {
            Schema::create('data_export_requests', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->index();
                $table->string('status', 24)->default('ready')->index();
                $table->json('payload')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
                $table->index(['user_id', 'requested_at']);
            });
        }

        if (! Schema::hasTable('account_deletion_requests')) {
            Schema::create('account_deletion_requests', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->index();
                $table->string('status', 24)->default('pending')->index();
                $table->string('reason', 255)->nullable();
                $table->string('blocking_reason', 255)->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('scheduled_for')->nullable()->index();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (Schema::hasTable('users')) {
            $needsDeletionSchedule = ! Schema::hasColumn('users', 'account_deletion_scheduled_for');
            $needsDeletedAt = ! Schema::hasColumn('users', 'account_deleted_at');

            if ($needsDeletionSchedule || $needsDeletedAt) {
                Schema::table('users', function (Blueprint $table) use ($needsDeletionSchedule, $needsDeletedAt): void {
                    if ($needsDeletionSchedule) {
                        $table->timestamp('account_deletion_scheduled_for')->nullable()->index();
                    }

                    if ($needsDeletedAt) {
                        $table->timestamp('account_deleted_at')->nullable()->index();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            $columns = [];
            if (Schema::hasColumn('users', 'account_deletion_scheduled_for')) {
                $columns[] = 'account_deletion_scheduled_for';
            }
            if (Schema::hasColumn('users', 'account_deleted_at')) {
                $columns[] = 'account_deleted_at';
            }

            if ($columns !== []) {
                Schema::table('users', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }

        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('data_export_requests');
    }
};
