<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class NotificationException extends HttpException
{
    public function __construct(int $statusCode, string $message, public readonly string $errorCode)
    {
        parent::__construct($statusCode, $message);
    }

    public static function unavailable(): self
    {
        return new self(404, 'The notification is unavailable.', 'notification_unavailable');
    }

    public static function circleUnavailable(): self
    {
        return new self(404, 'The Circle is unavailable.', 'notification_circle_unavailable');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => ['code' => $this->errorCode],
        ], $this->getStatusCode());
    }
}
