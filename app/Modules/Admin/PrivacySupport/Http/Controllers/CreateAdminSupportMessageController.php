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

final class CreateAdminSupportMessageController
{
    public function __invoke(Request $request, string $ticketId, SupportService $support, SupportPresenter $presenter): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:8000'],
            'attachment_refs' => ['nullable', 'array', 'max:10'],
            'attachment_refs.*' => ['string', 'max:200'],
            'internal' => ['nullable', 'boolean'],
        ]);

        $internal = (bool) ($data['internal'] ?? false);
        $requiredPermission = $internal ? 'support.notes.manage' : 'support.reply';
        if (! $admin->hasPermission($requiredPermission)) {
            return AdminApiResponse::error($request, 'You do not have permission to send this support message.', 'ADMIN_FORBIDDEN', 403);
        }

        $ticket = SupportTicket::query()->find($ticketId);
        if ($ticket === null) {
            return AdminApiResponse::error($request, 'Support ticket not found.', 'SUPPORT_TICKET_NOT_FOUND', 404);
        }

        try {
            $message = $support->adminMessage($ticket, $data, $admin, $session, $request);
        } catch (PrivacySupportDomainException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, $presenter->message($message, true), 201);
    }
}
