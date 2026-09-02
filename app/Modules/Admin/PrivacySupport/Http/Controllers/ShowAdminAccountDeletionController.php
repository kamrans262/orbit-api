<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Modules\Admin\PrivacySupport\Services\PrivacyPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowAdminAccountDeletionController
{
    public function __invoke(Request $request, string $deletionId, PrivacyPresenter $presenter): JsonResponse
    {
        $deletion = AccountDeletionRequest::query()->find($deletionId);
        if ($deletion === null) {
            return AdminApiResponse::error($request, 'Account deletion not found.', 'DELETION_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, $presenter->deletion($deletion));
    }
}
