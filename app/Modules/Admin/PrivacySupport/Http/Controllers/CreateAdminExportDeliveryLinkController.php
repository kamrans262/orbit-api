<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\DataExportRequest;
use App\Modules\Admin\PrivacySupport\Exceptions\PrivacySupportDomainException;
use App\Modules\Admin\PrivacySupport\Services\AdminDataExportService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateAdminExportDeliveryLinkController
{
    public function __invoke(Request $request, string $exportId, AdminDataExportService $service): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $export = DataExportRequest::query()->find($exportId);
        if ($export === null) {
            return AdminApiResponse::error($request, 'Data export not found.', 'EXPORT_NOT_FOUND', 404);
        }

        try {
            $payload = $service->createDeliveryLink($export, $admin, $session, $data['reason'], $request);
        } catch (PrivacySupportDomainException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, $payload, 201);
    }
}
