<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\PrivacySupport\Exceptions\PrivacySupportDomainException;
use App\Modules\Admin\PrivacySupport\Services\AdminDeletionService;
use App\Modules\Admin\PrivacySupport\Services\PrivacyPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CancelAdminAccountDeletionController
{
    public function __invoke(Request $request, string $deletionId, AdminDeletionService $service, PrivacyPresenter $presenter): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $deletion = AccountDeletionRequest::query()->find($deletionId);
        if ($deletion === null) {
            return AdminApiResponse::error($request, 'Account deletion not found.', 'DELETION_NOT_FOUND', 404);
        }

        try {
            $deletion = $service->cancel($deletion, $admin, $session, $data['reason'], $request);
        } catch (PrivacySupportDomainException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, $presenter->deletion($deletion));
    }
}
