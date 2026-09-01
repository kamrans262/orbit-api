<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->uuid('device_id')->nullable();
            $table->string('status', 20)->default('online');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->boolean('is_charging')->nullable();
            $table->string('network_type', 20)->nullable();
            $table->string('movement_type', 20)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_states');
    }
};
