<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\PrivacyRequest;
use App\Modules\Admin\Operations\Services\AdminAnnotationService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AddAdminPrivacyNoteController
{
    public function __invoke(Request $request, string $privacyRequestId, AdminAnnotationService $annotations): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        if (! PrivacyRequest::query()->whereKey($privacyRequestId)->exists()) {
            return AdminApiResponse::error($request, 'Privacy request not found.', 'PRIVACY_REQUEST_NOT_FOUND', 404);
        }

        $data = $request->validate(['note' => ['required', 'string', 'min:1', 'max:4000']]);
        $note = $annotations->addNote('privacy_request', $privacyRequestId, $data['note'], $admin, $session, $request);

        return AdminApiResponse::success($request, ['id' => $note->id], 201);
    }
}
