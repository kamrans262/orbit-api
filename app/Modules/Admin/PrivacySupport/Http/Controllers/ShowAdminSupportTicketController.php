<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\SupportTicket;
use App\Modules\Admin\PrivacySupport\Services\SupportPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowAdminSupportTicketController
{
    public function __invoke(Request $request, string $ticketId, SupportPresenter $presenter): JsonResponse
    {
        $ticket = SupportTicket::query()->find($ticketId);
        if ($ticket === null) {
            return AdminApiResponse::error($request, 'Support ticket not found.', 'SUPPORT_TICKET_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, $presenter->ticket($ticket, true));
    }
}
