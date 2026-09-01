<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\RequestEmailOtpAction;
use App\Modules\Auth\Exceptions\EmailOtpDeliveryException;
use App\Modules\Auth\Http\Requests\RequestEmailOtpRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RequestEmailOtpController extends Controller
{
    public function __invoke(
        RequestEmailOtpRequest $request,
        RequestEmailOtpAction $action,
    ): JsonResponse {
        try {
            $data = $action->handle($request->string('email')->toString());
        } catch (EmailOtpDeliveryException $exception) {
            report($exception);

            return ApiResponse::error(
                message: 'OTP delivery is currently unavailable.',
                code: 'OTP_DELIVERY_UNAVAILABLE',
                status: 503,
            );
        }

        return ApiResponse::success(
            data: $data,
            message: app()->environment('local')
                ? 'OTP sent. For local development, check storage/logs/laravel.log.'
                : 'OTP sent to your email address.',
            status: 202,
        );
    }
}
