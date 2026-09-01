<?php

declare(strict_types=1);

namespace App\Modules\Presence\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PresenceException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function invalidDevice(): self
    {
        return new self(
            'The selected device is not active or does not belong to this account.',
            'PRESENCE_DEVICE_INVALID',
            422,
        );
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            code: $this->errorCode,
            status: $this->status,
        );
    }
}
