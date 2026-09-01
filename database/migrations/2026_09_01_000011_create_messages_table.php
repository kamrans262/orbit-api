<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('sender_device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('client_sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['circle_id', 'created_at']);
            $table->index(['sender_user_id', 'created_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
