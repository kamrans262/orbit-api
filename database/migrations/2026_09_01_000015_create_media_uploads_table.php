<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('asset_id')->unique();
            $table->foreignUuid('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->foreignId('uploader_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('uploader_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('content_type_hint', 150)->nullable();
            $table->unsignedBigInteger('expected_size_bytes');
            $table->char('expected_sha256_ciphertext', 64);
            $table->unsignedInteger('chunk_size_bytes');
            $table->unsignedInteger('total_chunks');
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['circle_id', 'created_at']);
            $table->index(['uploader_user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
    }
};
