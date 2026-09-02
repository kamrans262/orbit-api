<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Admin\PrivacySupport\Services\SupportPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowConsumerSupportTicketController
{
    public function __invoke(Request $request, string $ticketId, SupportPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $ticket = SupportTicket::query()->whereKey($ticketId)->where('user_id', $user->id)->first();
        if ($ticket === null) {
            return response()->json(['success' => false, 'code' => 'SUPPORT_TICKET_NOT_FOUND'], 404);
        }

        return response()->json(['success' => true, 'data' => $presenter->ticket($ticket, false)]);
    }
}
