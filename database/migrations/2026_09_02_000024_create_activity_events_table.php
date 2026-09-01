<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('circle_id', 36)->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('event_type', 64)->index();
            $table->string('source_type', 64);
            $table->string('source_id', 64)->nullable();
            $table->string('event_key', 255)->unique();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('removed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['circle_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
