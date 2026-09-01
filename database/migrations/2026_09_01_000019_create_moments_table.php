<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['circle_id', 'status', 'expires_at', 'created_at']);
            $table->index(['author_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moments');
    }
};
