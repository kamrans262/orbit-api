<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->foreignUuid('sender_membership_id')->constrained('circle_members')->cascadeOnDelete();
            $table->foreignUuid('recipient_membership_id')->constrained('circle_members')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('response_type', 30)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_membership_id', 'status', 'expires_at']);
            $table->index(['sender_membership_id', 'created_at']);
            $table->index(['circle_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pings');
    }
};
