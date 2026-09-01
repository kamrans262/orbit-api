<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->string('location_mode', 20)->default('hidden');
            $table->boolean('can_ping')->default(true);
            $table->boolean('can_message')->default(true);
            $table->boolean('can_view_moments')->default(true);
            $table->boolean('activity_visibility')->default(true);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['circle_id', 'user_id']);
            $table->index(['circle_id', 'role']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_members');
    }
};
