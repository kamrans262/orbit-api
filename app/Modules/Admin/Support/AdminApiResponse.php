<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminApiResponse
{
    public static function success(Request $request, mixed $data = null, int $status = 200, string $message = 'Success.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'request_id' => self::requestId($request),
        ], $status);
    }

    public static function error(Request $request, string $message, string $code, int $status, ?array $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'code' => $code,
            'request_id' => self::requestId($request),
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function requestId(Request $request): ?string
    {
        $id = $request->attributes->get('admin_request_id');

        return is_string($id) ? $id : null;
    }
}
