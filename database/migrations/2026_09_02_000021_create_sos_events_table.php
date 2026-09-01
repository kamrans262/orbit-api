<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->uuid('circle_id');
            $table->string('status', 24)->index();
            $table->unsignedTinyInteger('escalation_stage')->default(0);
            $table->timestamp('activated_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->string('resolution_reason', 40)->nullable();
            $table->string('recording_ref', 255)->nullable();
            $table->timestamp('recording_expires_at')->nullable()->index();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->decimal('last_location_accuracy_m', 9, 2)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('circle_id')->references('id')->on('circles')->cascadeOnDelete();
            $table->index(['circle_id', 'status', 'activated_at']);
            $table->index(['user_id', 'activated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_events');
    }
};
