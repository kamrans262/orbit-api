<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Services;

use App\Models\ModerationEnforcement;
use App\Models\SecurityAuditLog;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketLink;
use Illuminate\Support\Facades\Schema;

final class SupportPresenter
{
    public function ticket(SupportTicket $ticket, bool $admin = true): array
    {
        $data = [
            'id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'subject' => $ticket->subject,
            'assigned_admin_id' => $admin ? $ticket->assigned_admin_id : null,
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'escalated_at' => $ticket->escalated_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];

        if ($admin) {
            $data['messages'] = SupportMessage::query()
                ->where('support_ticket_id', $ticket->id)
                ->oldest('created_at')
                ->get()
                ->map(fn (SupportMessage $message): array => $this->message($message, true))
                ->all();
            $data['links'] = SupportTicketLink::query()
                ->where('support_ticket_id', $ticket->id)
                ->get(['id', 'resource_type', 'resource_id', 'created_at'])
                ->toArray();
            $data['related_account_events'] = $this->relatedEvents((int) $ticket->user_id);
        } else {
            $data['messages'] = SupportMessage::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('internal', false)
                ->oldest('created_at')
                ->get()
                ->map(fn (SupportMessage $message): array => $this->message($message, false))
                ->all();
            unset($data['assigned_admin_id']);
        }

        return $data;
    }

    public function message(SupportMessage $message, bool $admin): array
    {
        return [
            'id' => $message->id,
            'actor_type' => $message->actor_type,
            'body' => $message->body,
            'attachment_refs' => $message->attachment_refs ?? [],
            'internal' => $admin ? $message->internal : false,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function relatedEvents(int $userId): array
    {
        $events = [];

        if (Schema::hasTable('audit_logs')) {
            $events = SecurityAuditLog::query()
                ->where('user_id', $userId)
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->map(fn (SecurityAuditLog $log): array => [
                    'type' => 'identity_audit',
                    'action' => $log->action,
                    'target_type' => $log->target_type,
                    'occurred_at' => $log->occurred_at?->toIso8601String(),
                ])->all();
        }

        if (Schema::hasTable('moderation_enforcements')) {
            $moderation = ModerationEnforcement::query()
                ->where('target_type', 'user')
                ->where('target_id', (string) $userId)
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (ModerationEnforcement $enforcement): array => [
                    'type' => 'moderation_enforcement',
                    'action' => $enforcement->action,
                    'status' => $enforcement->status,
                    'occurred_at' => $enforcement->created_at?->toIso8601String(),
                ])->all();

            $events = array_merge($events, $moderation);
        }

        usort($events, fn (array $a, array $b): int => strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? '')));

        return array_slice($events, 0, 15);
    }
}
