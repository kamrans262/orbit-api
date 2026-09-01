<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_upload_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('media_upload_id')->constrained('media_uploads')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('size_bytes');
            $table->char('sha256_ciphertext', 64);
            $table->string('storage_path', 500);
            $table->timestamps();

            $table->unique(['media_upload_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_upload_chunks');
    }
};
