<?php

declare(strict_types=1);

namespace App\Modules\Admin\BackendCompletion\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\ModerationReport;
use App\Models\SupportTicket;
use App\Modules\Admin\Moderation\Services\ModerationWorkflowService;
use App\Modules\Admin\PrivacySupport\Services\SupportService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminBulkOperationsController
{
    public function assignReports(Request $request, ModerationWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'report_ids' => ['required', 'array', 'min:1', 'max:100'],
            'report_ids.*' => ['uuid', 'distinct'],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:admin_users,id'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $reports = ModerationReport::query()->whereIn('id', $data['report_ids'])->get();
        if ($reports->count() !== count($data['report_ids'])) {
            return AdminApiResponse::error($request, 'One or more reports were not found.', 'BULK_REPORT_NOT_FOUND', 404);
        }
        $assignee = isset($data['assigned_admin_id']) ? AdminUser::query()->find($data['assigned_admin_id']) : null;
        $session = $request->attributes->get('admin_session');
        if (! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session is unavailable.', 'ADMIN_SESSION_REQUIRED', 401);
        }
        foreach ($reports as $report) {
            $workflow->assign($report, $assignee, $request->user(), $session, $data['reason'], $request);
        }

        return AdminApiResponse::success($request, ['updated' => $reports->count()]);
    }

    public function assignSupport(Request $request, SupportService $support): JsonResponse
    {
        $data = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1', 'max:100'],
            'ticket_ids.*' => ['uuid', 'distinct'],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:admin_users,id'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $tickets = SupportTicket::query()->whereIn('id', $data['ticket_ids'])->get();
        if ($tickets->count() !== count($data['ticket_ids'])) {
            return AdminApiResponse::error($request, 'One or more support tickets were not found.', 'BULK_SUPPORT_NOT_FOUND', 404);
        }
        $assignee = isset($data['assigned_admin_id']) ? AdminUser::query()->find($data['assigned_admin_id']) : null;
        $session = $request->attributes->get('admin_session');
        if (! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session is unavailable.', 'ADMIN_SESSION_REQUIRED', 401);
        }
        foreach ($tickets as $ticket) {
            $support->assign($ticket, $assignee, $request->user(), $session, $data['reason'], $request);
        }

        return AdminApiResponse::success($request, ['updated' => $tickets->count()]);
    }
}
