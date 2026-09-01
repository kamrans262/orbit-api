<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('type', 20)->default('standard');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['created_by', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circles');
    }
};
