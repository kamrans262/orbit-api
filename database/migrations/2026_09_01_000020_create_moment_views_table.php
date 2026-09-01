<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moment_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('moment_id')->constrained('moments')->cascadeOnDelete();
            $table->foreignId('viewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->unique(['moment_id', 'viewer_user_id']);
            $table->index(['moment_id', 'viewed_at']);
            $table->index(['viewer_user_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moment_views');
    }
};
