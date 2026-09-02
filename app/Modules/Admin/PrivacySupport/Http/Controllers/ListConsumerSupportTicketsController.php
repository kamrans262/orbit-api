<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Admin\PrivacySupport\Services\SupportPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListConsumerSupportTicketsController
{
    public function __invoke(Request $request, SupportPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $tickets = SupportTicket::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->get()
            ->map(fn (SupportTicket $ticket): array => $presenter->ticket($ticket, false));

        return response()->json(['success' => true, 'data' => $tickets]);
    }
}
