<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Admin\PrivacySupport\Exceptions\PrivacySupportDomainException;
use App\Modules\Admin\PrivacySupport\Services\SupportPresenter;
use App\Modules\Admin\PrivacySupport\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReplyConsumerSupportTicketController
{
    public function __invoke(Request $request, string $ticketId, SupportService $support, SupportPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:8000'],
            'attachment_refs' => ['nullable', 'array', 'max:10'],
            'attachment_refs.*' => ['string', 'max:200'],
        ]);

        $ticket = SupportTicket::query()->find($ticketId);
        if ($ticket === null || (int) $ticket->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'code' => 'SUPPORT_TICKET_NOT_FOUND'], 404);
        }

        try {
            $message = $support->consumerReply($user, $ticket, $data);
        } catch (PrivacySupportDomainException $exception) {
            return response()->json(['success' => false, 'code' => $exception->errorCode, 'message' => $exception->getMessage()], $exception->status);
        }

        return response()->json(['success' => true, 'data' => $presenter->message($message, false)], 201);
    }
}
