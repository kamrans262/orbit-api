<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_delivery_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('envelope_id')->unique();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('recipient_device_id')->constrained('devices')->cascadeOnDelete();
            $table->timestamp('delivered_at');
            $table->timestamps();

            $table->index(['message_id', 'recipient_user_id']);
            $table->index(['recipient_device_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_delivery_receipts');
    }
};
