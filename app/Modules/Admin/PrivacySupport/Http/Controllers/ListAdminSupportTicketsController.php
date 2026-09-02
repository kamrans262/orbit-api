<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\SupportTicket;
use App\Modules\Admin\PrivacySupport\Services\SupportPresenter;
use App\Modules\Admin\PrivacySupport\Services\SupportService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdminSupportTicketsController
{
    public function __invoke(Request $request, SupportService $support, SupportPresenter $presenter): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:24'],
            'priority' => ['nullable', 'string', 'max:16'],
            'category' => ['nullable', 'string', 'max:40'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'assigned_admin_id' => ['nullable', 'integer', 'min:1'],
            'unassigned' => ['nullable', 'boolean'],
            'sla_breached' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:160'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $page = $support->paginate($filters);

        return AdminApiResponse::success($request, [
            'items' => collect($page->items())->map(fn (SupportTicket $ticket): array => [
                'id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'subject' => $ticket->subject,
                'assigned_admin_id' => $ticket->assigned_admin_id,
                'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
                'last_message_at' => $ticket->last_message_at?->toIso8601String(),
                'updated_at' => $ticket->updated_at?->toIso8601String(),
            ])->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }
}
