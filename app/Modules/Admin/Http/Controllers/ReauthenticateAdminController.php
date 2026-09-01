<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Exceptions\AdminOperationException;
use App\Modules\Admin\Http\Requests\ReauthenticateAdminRequest;
use App\Modules\Admin\Services\AdminAuthenticationService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class ReauthenticateAdminController
{
    public function __invoke(ReauthenticateAdminRequest $request, AdminAuthenticationService $auth): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        /** @var AdminSession $session */
        $session = $request->attributes->get('admin_session');
        try {
            $recoveryUsed = $auth->reauthenticate($admin, $session, (string) $request->input('password'), (string) $request->input('code'), $request);
        } catch (AdminOperationException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, [
            'reauthenticated' => true,
            'valid_until' => now()->addMinutes(max(1, (int) config('orbit_admin.reauth_window_minutes', 10)))->toIso8601String(),
            'recovery_code_used' => $recoveryUsed,
        ]);
    }
}
