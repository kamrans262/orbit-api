<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 32);
            $table->string('source', 24)->default('consumer');
            $table->string('status', 32)->default('new');
            $table->string('identity_status', 32)->default('pending');
            $table->unsignedBigInteger('assigned_admin_id')->nullable();
            $table->text('details')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->uuid('linked_data_export_id')->nullable();
            $table->uuid('linked_deletion_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'privacy_req_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_admin_id', 'privacy_req_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('linked_data_export_id', 'privacy_req_export_fk')->references('id')->on('data_export_requests')->nullOnDelete();
            $table->foreign('linked_deletion_id', 'privacy_req_delete_fk')->references('id')->on('account_deletion_requests')->nullOnDelete();

            $table->index(['status', 'deadline_at'], 'privacy_req_status_deadline_idx');
            $table->index(['assigned_admin_id', 'status'], 'privacy_req_admin_status_idx');
            $table->index(['user_id', 'created_at'], 'privacy_req_user_created_idx');
            $table->unique('linked_data_export_id', 'privacy_req_export_uq');
            $table->unique('linked_deletion_id', 'privacy_req_delete_uq');
        });

        Schema::create('privacy_export_delivery_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('data_export_request_id');
            $table->unsignedBigInteger('user_id');
            $table->char('token_hash', 64)->unique('privacy_export_token_uq');
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('data_export_request_id', 'privacy_link_export_fk')->references('id')->on('data_export_requests')->cascadeOnDelete();
            $table->foreign('user_id', 'privacy_link_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by_admin_id', 'privacy_link_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->index(['user_id', 'expires_at'], 'privacy_link_user_exp_idx');
            $table->index(['data_export_request_id', 'revoked_at'], 'privacy_link_export_rev_idx');
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('category', 40);
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('new');
            $table->string('subject', 160);
            $table->unsignedBigInteger('assigned_admin_id')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'support_ticket_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_admin_id', 'support_ticket_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->index(['status', 'priority'], 'support_ticket_status_pri_idx');
            $table->index(['assigned_admin_id', 'status'], 'support_ticket_admin_status_idx');
            $table->index(['user_id', 'created_at'], 'support_ticket_user_created_idx');
            $table->index(['sla_due_at', 'status'], 'support_ticket_sla_status_idx');
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('support_ticket_id');
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->text('body');
            $table->json('attachment_refs')->nullable();
            $table->boolean('internal')->default(false);
            $table->timestamp('created_at');

            $table->foreign('support_ticket_id', 'support_msg_ticket_fk')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('actor_user_id', 'support_msg_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('actor_admin_id', 'support_msg_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->index(['support_ticket_id', 'created_at'], 'support_msg_ticket_time_idx');
        });

        Schema::create('support_ticket_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('support_ticket_id');
            $table->string('resource_type', 40);
            $table->string('resource_id', 100);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamp('created_at');

            $table->foreign('support_ticket_id', 'support_link_ticket_fk')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('created_by_admin_id', 'support_link_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->unique(['support_ticket_id', 'resource_type', 'resource_id'], 'support_link_resource_uq');
        });

        Schema::create('user_contact_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('channel', 24);
            $table->string('kind', 64);
            $table->string('direction', 16);
            $table->string('subject', 160)->nullable();
            $table->string('summary', 500)->nullable();
            $table->string('source_type', 40)->nullable();
            $table->string('source_id', 100)->nullable();
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');

            $table->foreign('user_id', 'contact_event_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('actor_admin_id', 'contact_event_admin_fk')->references('id')->on('admin_users')->nullOnDelete();
            $table->index(['user_id', 'occurred_at'], 'contact_event_user_time_idx');
            $table->index(['kind', 'occurred_at'], 'contact_event_kind_time_idx');
            $table->index(['source_type', 'source_id'], 'contact_event_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contact_events');
        Schema::dropIfExists('support_ticket_links');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('privacy_export_delivery_links');
        Schema::dropIfExists('privacy_requests');
    }
};
