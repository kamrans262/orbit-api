<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_hidden_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('activity_event_id', 36)->index();
            $table->timestamp('hidden_at');

            $table->unique(['user_id', 'activity_event_id']);
        });

        Schema::create('activity_reports', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('activity_event_id', 36)->index();
            $table->string('reason', 32);
            $table->string('details', 500)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamps();

            $table->unique(['user_id', 'activity_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_reports');
        Schema::dropIfExists('activity_hidden_events');
    }
};
