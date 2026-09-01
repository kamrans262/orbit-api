<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_key_envelopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->foreignUuid('recipient_device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('algorithm', 100);
            $table->text('encrypted_key');
            $table->timestamps();

            $table->unique(['media_asset_id', 'recipient_device_id']);
            $table->index(['recipient_device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_key_envelopes');
    }
};
