<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_escalations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sos_event_id');
            $table->unsignedTinyInteger('stage');
            $table->string('action', 80);
            $table->string('status', 40);
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->foreign('sos_event_id')->references('id')->on('sos_events')->cascadeOnDelete();
            $table->unique(['sos_event_id', 'stage']);
        });

        Schema::create('sos_notification_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sos_event_id');
            $table->unsignedBigInteger('target_user_id');
            $table->string('channel', 24);
            $table->string('kind', 80);
            $table->string('priority', 24)->default('highest');
            $table->json('payload');
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('available_at')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->foreign('sos_event_id')->references('id')->on('sos_events')->cascadeOnDelete();
            $table->foreign('target_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['sos_event_id', 'target_user_id', 'channel', 'kind'], 'sos_outbox_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_notification_outbox');
        Schema::dropIfExists('sos_escalations');
    }
};
