<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Exceptions\AdminOperationException;
use App\Modules\Admin\Http\Requests\LoginAdminRequest;
use App\Modules\Admin\Services\AdminAuthenticationService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class LoginAdminController
{
    public function __invoke(LoginAdminRequest $request, AdminAuthenticationService $auth): JsonResponse
    {
        try {
            $challenge = $auth->passwordLogin((string) $request->input('email'), (string) $request->input('password'), $request);
        } catch (AdminOperationException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, [
            'mfa_required' => true,
            'challenge_token' => $challenge['challenge_token'],
            'expires_at' => $challenge['expires_at'],
        ]);
    }
}
