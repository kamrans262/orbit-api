<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Http\Requests\UpdateAdminStatusRequest;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Services\AdminSessionService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class UpdateAdminStatusController
{
    public function __invoke(UpdateAdminStatusRequest $request, string $adminId, AdminSessionService $sessions, AdminAuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user();
        /** @var AdminSession $actorSession */
        $actorSession = $request->attributes->get('admin_session');
        $target = AdminUser::query()->find($adminId);
        if ($target === null) {
            return AdminApiResponse::error($request, 'Administrator not found.', 'ADMIN_NOT_FOUND', 404);
        }
        if ($target->id === $actor->id) {
            return AdminApiResponse::error($request, 'Administrators cannot change their own account status.', 'ADMIN_SELF_STATUS_FORBIDDEN', 409);
        }

        $newStatus = AdminStatus::from((string) $request->input('status'));
        if ($newStatus === AdminStatus::Active && $target->mfa_confirmed_at === null) {
            return AdminApiResponse::error($request, 'MFA must be activated before an administrator can be reactivated.', 'ADMIN_MFA_REQUIRED', 409);
        }

        $expiryWasSupplied = $request->exists('access_expires_at');
        if ($newStatus === AdminStatus::Active && $target->access_expires_at?->isPast() && ! $expiryWasSupplied) {
            return AdminApiResponse::error(
                $request,
                'Supply a future access_expires_at value or null to explicitly convert this expired temporary account to permanent access.',
                'ADMIN_ACCESS_EXPIRY_REQUIRED',
                422,
            );
        }

        $newAccessExpiry = $target->access_expires_at;
        if ($expiryWasSupplied) {
            $newAccessExpiry = $request->input('access_expires_at') === null
                ? null
                : Carbon::parse((string) $request->input('access_expires_at'));
        }

        $before = [
            'status' => $target->status->value,
            'access_expires_at' => $target->access_expires_at?->toIso8601String(),
        ];
        $target->forceFill([
            'status' => $newStatus,
            'disabled_at' => $newStatus === AdminStatus::Disabled ? now() : null,
            'access_expires_at' => $newAccessExpiry,
        ])->save();

        $revoked = $newStatus === AdminStatus::Disabled ? $sessions->revokeAll($target, 'administrator_disabled') : 0;
        $audit->write(
            $newStatus === AdminStatus::Disabled ? 'admin.account.disabled' : 'admin.account.reactivated',
            $actor,
            $actorSession,
            'admin_user',
            $target->id,
            reason: (string) $request->input('reason'),
            before: $before,
            after: [
                'status' => $target->status->value,
                'access_expires_at' => $target->access_expires_at?->toIso8601String(),
                'sessions_revoked' => $revoked,
            ],
            request: $request,
        );

        return AdminApiResponse::success($request, [
            'id' => $target->id,
            'status' => $target->status->value,
            'access_expires_at' => $target->access_expires_at?->toIso8601String(),
            'sessions_revoked' => $revoked,
        ]);
    }
}
