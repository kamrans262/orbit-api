<?php

declare(strict_types=1);

namespace App\Modules\Ping\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PingException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function circleNotFound(): self
    {
        return new self('Circle not found.', 'PING_CIRCLE_NOT_FOUND', 404);
    }

    public static function recipientNotFound(): self
    {
        return new self('Ping recipient not found.', 'PING_RECIPIENT_NOT_FOUND', 404);
    }

    public static function selfPingNotAllowed(): self
    {
        return new self('You cannot Ping yourself.', 'PING_SELF_NOT_ALLOWED', 422);
    }

    public static function recipientDisabledPings(): self
    {
        return new self('This member is not accepting Pings.', 'PING_DISABLED', 403);
    }

    public static function cooldown(): self
    {
        return new self('Please wait a moment before Ping-ing this member again.', 'PING_COOLDOWN', 429);
    }

    public static function notFound(): self
    {
        return new self('Ping not found.', 'PING_NOT_FOUND', 404);
    }

    public static function forbidden(): self
    {
        return new self('You do not have permission to act on this Ping.', 'PING_FORBIDDEN', 403);
    }

    public static function notPending(): self
    {
        return new self('This Ping is no longer awaiting a response.', 'PING_NOT_PENDING', 409);
    }

    public static function expired(): self
    {
        return new self('This Ping has expired.', 'PING_EXPIRED', 410);
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
