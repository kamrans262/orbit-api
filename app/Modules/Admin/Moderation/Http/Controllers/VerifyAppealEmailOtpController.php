<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Modules\Admin\Moderation\Actions\VerifyAppealEmailOtpAction;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Auth\Exceptions\EmailOtpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerifyAppealEmailOtpController
{
    public function __invoke(Request $request, VerifyAppealEmailOtpAction $action): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'enforcement_id' => ['required', 'uuid'],
        ]);

        try {
            $result = $action->handle(
                (string) $data['email'],
                (string) $data['otp'],
                (string) $data['enforcement_id'],
            );
        } catch (EmailOtpException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => $exception->apiCode,
            ], $exception->status);
        } catch (ModerationDomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->status);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
