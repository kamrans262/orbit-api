<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\PrivacyRequest;
use App\Modules\Admin\PrivacySupport\Services\PrivacyPresenter;
use App\Modules\Admin\PrivacySupport\Services\PrivacyWorkflowService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class VerifyAdminPrivacyIdentityController
{
    public function __invoke(Request $request, string $privacyRequestId, PrivacyWorkflowService $workflow, PrivacyPresenter $presenter): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $data = $request->validate([
            'method' => ['required', Rule::in(['account_access_confirmed', 'support_verified', 'provider_verified'])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $privacy = PrivacyRequest::query()->find($privacyRequestId);
        if ($privacy === null) {
            return AdminApiResponse::error($request, 'Privacy request not found.', 'PRIVACY_REQUEST_NOT_FOUND', 404);
        }

        $privacy = $workflow->verifyIdentity($privacy, $data['method'], $admin, $session, $data['reason'], $request);

        return AdminApiResponse::success($request, $presenter->request($privacy));
    }
}
