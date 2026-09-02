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

final class AssignAdminSupportTicketController
{
    public function __invoke(Request $request, string $ticketId, SupportService $support, SupportPresenter $presenter): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $data = $request->validate([
            'assigned_admin_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $ticket = SupportTicket::query()->find($ticketId);
        if ($ticket === null) {
            return AdminApiResponse::error($request, 'Support ticket not found.', 'SUPPORT_TICKET_NOT_FOUND', 404);
        }

        $assignee = isset($data['assigned_admin_id']) ? AdminUser::query()->find($data['assigned_admin_id']) : null;
        if (isset($data['assigned_admin_id']) && $assignee === null) {
            return AdminApiResponse::error($request, 'Support assignee not found.', 'SUPPORT_ASSIGNEE_INVALID', 422);
        }

        try {
            $ticket = $support->assign($ticket, $assignee, $admin, $session, $data['reason'], $request);
        } catch (PrivacySupportDomainException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, $presenter->ticket($ticket, true));
    }
}
