<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\DataExportRequest;
use App\Models\PrivacyExportDeliveryLink;
use App\Modules\Admin\PrivacySupport\Exceptions\PrivacySupportDomainException;
use App\Modules\Admin\PrivacySupport\Services\AdminDataExportService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevokeAdminExportDeliveryLinkController
{
    public function __invoke(Request $request, string $exportId, string $linkId, AdminDataExportService $service): JsonResponse
    {
        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $export = DataExportRequest::query()->find($exportId);
        $link = PrivacyExportDeliveryLink::query()->find($linkId);
        if ($export === null || $link === null) {
            return AdminApiResponse::error($request, 'Export delivery link not found.', 'EXPORT_LINK_NOT_FOUND', 404);
        }

        try {
            $service->revokeDeliveryLink($export, $link, $admin, $session, $data['reason'], $request);
        } catch (PrivacySupportDomainException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, ['revoked' => true]);
    }
}
