<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_invites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->unsignedSmallInteger('max_uses')->default(10);
            $table->unsignedSmallInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['circle_id', 'expires_at', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_invites');
    }
};
