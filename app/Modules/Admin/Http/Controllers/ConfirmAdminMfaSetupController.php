<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Exceptions\AdminOperationException;
use App\Modules\Admin\Http\Requests\ConfirmAdminMfaSetupRequest;
use App\Modules\Admin\Services\AdminAuthenticationService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class ConfirmAdminMfaSetupController
{
    public function __invoke(ConfirmAdminMfaSetupRequest $request, AdminAuthenticationService $auth): JsonResponse
    {
        try {
            $codes = $auth->confirmMfaSetup((string) $request->input('setup_token'), (string) $request->input('code'), $request);
        } catch (AdminOperationException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, [
            'activated' => true,
            'recovery_codes' => $codes,
            'recovery_codes_shown_once' => true,
        ]);
    }
}
