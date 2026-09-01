<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_envelopes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('envelope_id')->unique();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('recipient_device_id')->constrained('devices')->cascadeOnDelete();
            $table->longText('ciphertext');
            $table->text('encrypted_preview')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['recipient_device_id', 'id']);
            $table->index(['recipient_user_id', 'id']);
            $table->index(['message_id', 'recipient_device_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_envelopes');
    }
};
