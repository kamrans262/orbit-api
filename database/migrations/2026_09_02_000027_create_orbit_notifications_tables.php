<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orbit_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->uuid('circle_id')->nullable();
            $table->string('kind', 80)->index();
            $table->string('priority', 24)->default('normal')->index();
            $table->string('idempotency_key', 190)->unique();
            $table->string('summary', 120);
            $table->json('payload');
            $table->string('deep_link', 500)->nullable();
            $table->boolean('in_app_visible')->default(true)->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('circle_id')->references('id')->on('circles')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id');
            $table->unsignedBigInteger('target_user_id');
            $table->string('device_id', 64);
            $table->string('channel', 24)->default('push');
            $table->string('provider', 24);
            $table->string('priority', 24);
            $table->string('collapse_key', 190)->nullable();
            $table->boolean('silent')->default(false);
            $table->json('payload');
            $table->string('status', 32)->default('pending_provider')->index();
            $table->timestamp('available_at')->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
            $table->foreign('notification_id')->references('id')->on('orbit_notifications')->cascadeOnDelete();
            $table->foreign('target_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['notification_id', 'device_id', 'channel'], 'notification_delivery_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('orbit_notifications');
    }
};
