<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Exceptions\AdminOperationException;
use App\Modules\Admin\Http\Requests\VerifyAdminMfaRequest;
use App\Modules\Admin\Services\AdminAuthenticationService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class VerifyAdminMfaController
{
    public function __invoke(VerifyAdminMfaRequest $request, AdminAuthenticationService $auth): JsonResponse
    {
        try {
            $result = $auth->verifyLoginMfa((string) $request->input('challenge_token'), (string) $request->input('code'), $request);
        } catch (AdminOperationException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, [
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'session_id' => $result['session']->id,
            'expires_at' => $result['session']->expires_at->toIso8601String(),
            'idle_expires_at' => $result['session']->idle_expires_at->toIso8601String(),
            'recovery_code_used' => $result['recovery_code_used'],
        ]);
    }
}
