<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Modules\Admin\PrivacySupport\Services\PrivacyPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowAdminPrivacyRequestController
{
    public function __invoke(Request $request, string $privacyRequestId, PrivacyPresenter $presenter): JsonResponse
    {
        $privacy = PrivacyRequest::query()->find($privacyRequestId);
        if ($privacy === null) {
            return AdminApiResponse::error($request, 'Privacy request not found.', 'PRIVACY_REQUEST_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, $presenter->request($privacy));
    }
}
