<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\SosEvent;
use App\Modules\Admin\Safety\Http\Requests\UpdateAdminSosClassificationRequest;
use App\Modules\Admin\Safety\Services\AdminSosOperationsService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAdminSosClassificationController
{
    public function __invoke(UpdateAdminSosClassificationRequest $request, string $sosId, AdminSosOperationsService $service): JsonResponse
    {
        $incident = SosEvent::query()->find($sosId);
        if ($incident === null) {
            return AdminApiResponse::error($request, 'SOS incident not found.', 'ADMIN_SOS_NOT_FOUND', 404);
        }

        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session is unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $payload = $request->safe()->except('reason');

        try {
            $control = $service->updateClassification(
                $incident,
                $admin,
                $session,
                $payload,
                (string) $request->validated('reason'),
                $request,
            );
        } catch (\DomainException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), 'ADMIN_SOS_OPERATION_CONFLICT', 409);
        }

        return AdminApiResponse::success($request, $service->snapshot($control));
    }
}
