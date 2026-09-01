<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_responders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sos_event_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('engaged_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->decimal('last_location_accuracy_m', 9, 2)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->timestamps();

            $table->foreign('sos_event_id')->references('id')->on('sos_events')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['sos_event_id', 'user_id']);
            $table->index(['sos_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_responders');
    }
};
