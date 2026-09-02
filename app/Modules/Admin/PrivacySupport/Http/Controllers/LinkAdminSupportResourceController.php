<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\SupportTicket;
use App\Modules\Admin\PrivacySupport\Services\SupportService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LinkAdminSupportResourceController
{
    public function __invoke(Request $request, string $ticketId, SupportService $support): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $data = $request->validate([
            'resource_type' => ['required', Rule::in(['privacy_request', 'data_export', 'account_deletion', 'moderation_report', 'moderation_appeal', 'sos_event'])],
            'resource_id' => ['required', 'string', 'max:100'],
        ]);

        $ticket = SupportTicket::query()->find($ticketId);
        if ($ticket === null) {
            return AdminApiResponse::error($request, 'Support ticket not found.', 'SUPPORT_TICKET_NOT_FOUND', 404);
        }

        $link = $support->linkResource($ticket, $data['resource_type'], $data['resource_id'], $admin, $session, $request);

        return AdminApiResponse::success($request, [
            'id' => $link->id,
            'resource_type' => $link->resource_type,
            'resource_id' => $link->resource_id,
        ], 201);
    }
}
