<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\VerifyEmailOtpAction;
use App\Modules\Auth\Exceptions\EmailOtpException;
use App\Modules\Auth\Http\Requests\VerifyEmailOtpRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class VerifyEmailOtpController extends Controller
{
    public function __invoke(
        VerifyEmailOtpRequest $request,
        VerifyEmailOtpAction $action,
    ): JsonResponse {
        try {
            $result = $action->handle(
                email: $request->string('email')->toString(),
                otpCode: $request->string('otp')->toString(),
                deviceName: $request->string('device_name')->toString(),
            );
        } catch (EmailOtpException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                code: $exception->apiCode,
                status: $exception->status,
            );
        }

        return ApiResponse::success(
            data: [
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'email_verified_at' => $result['user']->email_verified_at?->toIso8601String(),
                ],
                'access_token' => $result['token'],
                'token_type' => $result['token_type'],
                'expires_at' => $result['expires_at'],
            ],
            message: 'Email verified successfully.',
        );
    }
}
