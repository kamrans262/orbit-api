<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Exceptions;

use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class CommunicationsContentException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        if (str_starts_with($request->path(), 'api/admin/')) {
            return AdminApiResponse::error($request, $this->getMessage(), $this->errorCode, $this->status);
        }

        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->status);
    }
}
