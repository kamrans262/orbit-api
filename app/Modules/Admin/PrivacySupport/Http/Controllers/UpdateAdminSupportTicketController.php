<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\SupportTicket;
use App\Modules\Admin\PrivacySupport\Exceptions\PrivacySupportDomainException;
use App\Modules\Admin\PrivacySupport\Services\SupportPresenter;
use App\Modules\Admin\PrivacySupport\Services\SupportService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class UpdateAdminSupportTicketController
{
    public function __invoke(Request $request, string $ticketId, SupportService $support, SupportPresenter $presenter): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'max:24'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'sla_due_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $ticket = SupportTicket::query()->find($ticketId);
        if ($ticket === null) {
            return AdminApiResponse::error($request, 'Support ticket not found.', 'SUPPORT_TICKET_NOT_FOUND', 404);
        }

        try {
            $ticket = $support->update($ticket, $data, $admin, $session, $request);
        } catch (PrivacySupportDomainException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, $presenter->ticket($ticket, true));
    }
}
