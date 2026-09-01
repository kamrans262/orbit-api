<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureIncidentControlsTable();
        $this->ensureNotesTable();
        $this->ensureSensitiveAccessLogsTable();
        $this->ensureExportsTable();
    }

    private function ensureIncidentControlsTable(): void
    {
        if (! Schema::hasTable('admin_sos_incident_controls')) {
            Schema::create('admin_sos_incident_controls', function (Blueprint $table): void {
                $table->uuid('sos_event_id')->primary();
                $table->foreignId('assigned_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->string('operational_status', 24)->default('open')->index();
                $table->string('internal_escalation_level', 24)->default('normal')->index();
                $table->boolean('false_alarm')->default(false)->index();
                $table->boolean('technical_failure')->default(false)->index();
                $table->boolean('abuse_flag')->default(false)->index();
                $table->string('operational_resolution', 500)->nullable();
                $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('sos_event_id')
                    ->references('id')
                    ->on('sos_events')
                    ->cascadeOnDelete();

                $table->index(
                    ['assigned_admin_id', 'operational_status'],
                    'admin_sos_ctrl_assign_status_idx',
                );
            });

            return;
        }

        // A failed MySQL DDL migration may leave this table behind because
        // CREATE / ALTER statements are not rolled back atomically. Repair
        // the missing composite index before continuing the migration.
        if (! Schema::hasIndex(
            'admin_sos_incident_controls',
            ['assigned_admin_id', 'operational_status'],
        )) {
            Schema::table('admin_sos_incident_controls', function (Blueprint $table): void {
                $table->index(
                    ['assigned_admin_id', 'operational_status'],
                    'admin_sos_ctrl_assign_status_idx',
                );
            });
        }
    }

    private function ensureNotesTable(): void
    {
        if (Schema::hasTable('admin_sos_notes')) {
            return;
        }

        Schema::create('admin_sos_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sos_event_id')->index();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('sos_event_id')
                ->references('id')
                ->on('sos_events')
                ->cascadeOnDelete();

            $table->index(
                ['sos_event_id', 'created_at'],
                'admin_sos_notes_event_created_idx',
            );
        });
    }

    private function ensureSensitiveAccessLogsTable(): void
    {
        if (Schema::hasTable('admin_sos_sensitive_access_logs')) {
            return;
        }

        Schema::create('admin_sos_sensitive_access_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sos_event_id')->index();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->uuid('admin_session_id')->nullable()->index();
            $table->string('access_type', 24)->index();
            $table->string('purpose', 80);
            $table->string('reason', 500);
            $table->string('request_id', 80)->nullable()->index();
            $table->timestamp('occurred_at')->index();

            $table->foreign('sos_event_id')
                ->references('id')
                ->on('sos_events')
                ->cascadeOnDelete();

            $table->foreign('admin_session_id')
                ->references('id')
                ->on('admin_sessions')
                ->nullOnDelete();

            $table->index(
                ['admin_user_id', 'occurred_at'],
                'admin_sos_access_admin_time_idx',
            );
        });
    }

    private function ensureExportsTable(): void
    {
        if (Schema::hasTable('admin_sos_exports')) {
            return;
        }

        Schema::create('admin_sos_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sos_event_id')->index();
            $table->foreignId('requested_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('format', 12)->default('json');
            $table->string('status', 24)->default('ready')->index();
            $table->json('snapshot');
            $table->timestamp('requested_at')->index();
            $table->timestamp('expires_at')->index();

            $table->foreign('sos_event_id')
                ->references('id')
                ->on('sos_events')
                ->cascadeOnDelete();

            $table->index(
                ['requested_by_admin_id', 'requested_at'],
                'admin_sos_exports_requester_time_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_sos_exports');
        Schema::dropIfExists('admin_sos_sensitive_access_logs');
        Schema::dropIfExists('admin_sos_notes');
        Schema::dropIfExists('admin_sos_incident_controls');
    }
};
