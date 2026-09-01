<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('messages_enabled')->default(true);
            $table->boolean('moments_enabled')->default(true);
            $table->boolean('pings_enabled')->default(true);
            $table->boolean('activity_enabled')->default(true);
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('circle_notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->uuid('circle_id');
            $table->timestamp('muted_until')->nullable()->index();
            $table->boolean('silent')->default(false);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('circle_id')->references('id')->on('circles')->cascadeOnDelete();
            $table->unique(['user_id', 'circle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_notification_preferences');
        Schema::dropIfExists('notification_preferences');
    }
};
