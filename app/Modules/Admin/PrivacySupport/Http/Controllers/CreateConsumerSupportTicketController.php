<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\PrivacySupport\Services\SupportPresenter;
use App\Modules\Admin\PrivacySupport\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateConsumerSupportTicketController
{
    public function __invoke(Request $request, SupportService $support, SupportPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'category' => ['required', Rule::in(['account', 'technical', 'safety', 'privacy', 'billing', 'other'])],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:3', 'max:8000'],
            'attachment_refs' => ['nullable', 'array', 'max:10'],
            'attachment_refs.*' => ['string', 'max:200'],
        ]);

        $ticket = $support->createConsumer($user, $data);

        return response()->json(['success' => true, 'data' => $presenter->ticket($ticket, false)], 201);
    }
}
