<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\DataExportRequest;
use App\Modules\Admin\PrivacySupport\Services\PrivacyPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowAdminDataExportController
{
    public function __invoke(Request $request, string $exportId, PrivacyPresenter $presenter): JsonResponse
    {
        $export = DataExportRequest::query()->find($exportId);
        if ($export === null) {
            return AdminApiResponse::error($request, 'Data export not found.', 'EXPORT_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, $presenter->export($export));
    }
}
