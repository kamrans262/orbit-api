<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Success.',
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $errors
     */
    public static function error(
        string $message,
        string $code,
        int $status,
        ?array $errors = null,
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
