<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Admin\PrivacySupport\Services\SupportPresenter;
use App\Modules\Admin\PrivacySupport\Services\SupportService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateAdminSupportTicketController
{
    public function __invoke(Request $request, SupportService $support, SupportPresenter $presenter): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'category' => ['required', Rule::in(['account', 'technical', 'safety', 'privacy', 'billing', 'other'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['nullable', 'string', 'max:8000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $user = User::query()->find($data['user_id']);
        if ($user === null) {
            return AdminApiResponse::error($request, 'User not found.', 'USER_NOT_FOUND', 404);
        }

        $ticket = $support->createAdmin($user, $data, $admin, $session, $request);

        return AdminApiResponse::success($request, $presenter->ticket($ticket, true), 201);
    }
}
