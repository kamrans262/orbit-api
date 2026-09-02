<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketLink;
use App\Models\User;
use App\Modules\Admin\PrivacySupport\Exceptions\PrivacySupportDomainException;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class SupportService
{
    public function __construct(
        private AdminAuditLogger $audit,
        private ContactHistoryService $contacts,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = SupportTicket::query();

        foreach (['status', 'priority', 'category'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['assigned_admin_id'])) {
            $query->where('assigned_admin_id', (int) $filters['assigned_admin_id']);
        }
        if (filter_var($filters['unassigned'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereNull('assigned_admin_id');
        }
        if (filter_var($filters['sla_breached'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->whereNotIn('status', ['resolved', 'closed']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
            $query->where(fn ($q) => $q->where('subject', 'like', $term)->orWhere('id', 'like', $term));
        }

        return $query->latest('updated_at')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
    }

    public function createConsumer(User $user, array $data): SupportTicket
    {
        return DB::transaction(function () use ($user, $data): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'user_id' => $user->id,
                'category' => $data['category'],
                'priority' => 'normal',
                'status' => 'new',
                'subject' => $data['subject'],
                'sla_due_at' => now()->addHours(24),
                'last_message_at' => now(),
            ]);

            SupportMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'actor_type' => 'user',
                'actor_user_id' => $user->id,
                'body' => $data['message'],
                'attachment_refs' => $data['attachment_refs'] ?? [],
                'internal' => false,
            ]);

            $this->contacts->record(
                (int) $user->id, 'support.ticket.created', 'support', 'inbound',
                $ticket->subject, 'Support ticket created.', 'support_ticket', $ticket->id,
            );

            return $ticket->refresh();
        });
    }

    public function createAdmin(
        User $user,
        array $data,
        AdminUser $admin,
        AdminSession $session,
        Request $request,
    ): SupportTicket {
        $ticket = DB::transaction(function () use ($user, $data, $admin): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'user_id' => $user->id,
                'category' => $data['category'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'open',
                'subject' => $data['subject'],
                'assigned_admin_id' => $admin->id,
                'sla_due_at' => now()->addHours(24),
                'last_message_at' => now(),
            ]);

            if (! empty($data['message'])) {
                SupportMessage::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'actor_type' => 'admin',
                    'actor_admin_id' => $admin->id,
                    'body' => $data['message'],
                    'attachment_refs' => [],
                    'internal' => false,
                ]);
            }

            return $ticket->refresh();
        });

        $this->audit->write(
            'admin.support.ticket.created', $admin, $session, 'support_ticket', $ticket->id,
            reason: $data['reason'], after: ['user_id' => $user->id, 'category' => $ticket->category, 'priority' => $ticket->priority],
            request: $request,
        );

        $this->contacts->record(
            (int) $user->id, 'support.ticket.created_by_admin', 'support', 'outbound',
            $ticket->subject, 'Orbit Support opened a support case.', 'support_ticket', $ticket->id, $admin,
        );

        if (class_exists(RouteNotificationAction::class)) {
            app(RouteNotificationAction::class)->handle(
                (int) $user->id,
                'support.case_opened',
                'support-case-opened:'.$ticket->id,
                ['resource_id' => $ticket->id, 'actor_user_id' => (int) $user->id, 'deep_link' => '/profile/support/'.$ticket->id],
                NotificationPriority::Normal,
            );
        }

        return $ticket;
    }

    public function consumerReply(User $user, SupportTicket $ticket, array $data): SupportMessage
    {
        if ((int) $ticket->user_id !== (int) $user->id) {
            throw new PrivacySupportDomainException('SUPPORT_TICKET_NOT_FOUND', 404, 'Support ticket not found.');
        }
        if ($ticket->status === 'closed') {
            throw new PrivacySupportDomainException('SUPPORT_TICKET_CLOSED', 409, 'A closed support ticket cannot receive new replies.');
        }

        $message = SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'actor_type' => 'user',
            'actor_user_id' => $user->id,
            'body' => $data['message'],
            'attachment_refs' => $data['attachment_refs'] ?? [],
            'internal' => false,
        ]);

        $ticket->forceFill([
            'status' => $ticket->status === 'resolved' ? 'open' : $ticket->status,
            'last_message_at' => now(),
            'resolved_at' => $ticket->status === 'resolved' ? null : $ticket->resolved_at,
        ])->save();

        $this->contacts->record(
            (int) $user->id, 'support.user_reply', 'support', 'inbound',
            $ticket->subject, 'User replied to support.', 'support_ticket', $ticket->id,
        );

        return $message;
    }

    public function assign(SupportTicket $ticket, ?AdminUser $assignee, AdminUser $actor, AdminSession $session, string $reason, Request $request): SupportTicket
    {
        if ($assignee !== null && (! $assignee->isOperationallyActive() || ! $assignee->hasPermission('support.view'))) {
            throw new PrivacySupportDomainException('SUPPORT_ASSIGNEE_INVALID', 422, 'The selected administrator is not eligible for support work.');
        }

        $before = ['assigned_admin_id' => $ticket->assigned_admin_id];
        $ticket->forceFill(['assigned_admin_id' => $assignee?->id, 'status' => $ticket->status === 'new' ? 'open' : $ticket->status])->save();

        $this->audit->write(
            'admin.support.assignment.updated', $actor, $session, 'support_ticket', $ticket->id,
            reason: $reason, before: $before, after: ['assigned_admin_id' => $ticket->assigned_admin_id], request: $request,
        );

        return $ticket->refresh();
    }

    public function update(SupportTicket $ticket, array $data, AdminUser $actor, AdminSession $session, Request $request): SupportTicket
    {
        $statuses = ['new', 'open', 'pending_user', 'in_progress', 'escalated', 'resolved', 'closed'];
        if (! in_array($data['status'], $statuses, true)) {
            throw new PrivacySupportDomainException('SUPPORT_STATUS_INVALID', 422, 'Unsupported support status.');
        }
        if ($ticket->status === 'closed' && $data['status'] !== 'closed') {
            throw new PrivacySupportDomainException('SUPPORT_TICKET_FINAL', 409, 'A closed support ticket cannot be reopened administratively.');
        }

        $before = [
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
        ];

        $ticket->forceFill([
            'status' => $data['status'],
            'priority' => $data['priority'] ?? $ticket->priority,
            'sla_due_at' => array_key_exists('sla_due_at', $data) ? $data['sla_due_at'] : $ticket->sla_due_at,
            'escalated_at' => $data['status'] === 'escalated' ? ($ticket->escalated_at ?? now()) : $ticket->escalated_at,
            'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null,
        ])->save();

        $this->audit->write(
            'admin.support.workflow.updated', $actor, $session, 'support_ticket', $ticket->id,
            reason: $data['reason'], before: $before,
            after: ['status' => $ticket->status, 'priority' => $ticket->priority, 'sla_due_at' => $ticket->sla_due_at?->toIso8601String()],
            request: $request,
        );

        return $ticket->refresh();
    }

    public function adminMessage(SupportTicket $ticket, array $data, AdminUser $admin, AdminSession $session, Request $request): SupportMessage
    {
        if ($ticket->status === 'closed') {
            throw new PrivacySupportDomainException('SUPPORT_TICKET_CLOSED', 409, 'A closed support ticket cannot receive replies.');
        }

        $internal = (bool) ($data['internal'] ?? false);
        $message = SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'actor_type' => 'admin',
            'actor_admin_id' => $admin->id,
            'body' => $data['message'],
            'attachment_refs' => $data['attachment_refs'] ?? [],
            'internal' => $internal,
        ]);

        $ticket->forceFill([
            'status' => $internal ? $ticket->status : 'pending_user',
            'last_message_at' => now(),
        ])->save();

        $this->audit->write(
            $internal ? 'admin.support.note.created' : 'admin.support.reply.sent',
            $admin, $session, 'support_ticket', $ticket->id,
            after: ['message_id' => $message->id, 'internal' => $internal], request: $request,
        );

        if (! $internal) {
            $this->contacts->record(
                (int) $ticket->user_id, 'support.admin_reply', 'support', 'outbound',
                $ticket->subject, 'Orbit Support replied to your ticket.', 'support_ticket', $ticket->id, $admin,
            );

            if (class_exists(RouteNotificationAction::class)) {
                app(RouteNotificationAction::class)->handle(
                    (int) $ticket->user_id,
                    'support.reply',
                    'support-reply:'.$message->id,
                    ['resource_id' => $ticket->id, 'actor_user_id' => (int) $ticket->user_id, 'deep_link' => '/profile/support/'.$ticket->id],
                    NotificationPriority::Normal,
                );
            }
        }

        return $message;
    }

    public function linkResource(SupportTicket $ticket, string $resourceType, string $resourceId, AdminUser $admin, AdminSession $session, Request $request): SupportTicketLink
    {
        $link = SupportTicketLink::query()->firstOrCreate(
            ['support_ticket_id' => $ticket->id, 'resource_type' => $resourceType, 'resource_id' => $resourceId],
            ['created_by_admin_id' => $admin->id, 'created_at' => now()],
        );

        if ($link->wasRecentlyCreated) {
            $this->audit->write(
                'admin.support.resource.linked', $admin, $session, 'support_ticket', $ticket->id,
                after: ['resource_type' => $resourceType, 'resource_id' => $resourceId], request: $request,
            );
        }

        return $link;
    }
}
