<?php

declare(strict_types=1);

namespace App\Modules\Activity\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ActivityException extends HttpException
{
    public function __construct(
        int $statusCode,
        string $message,
        public readonly string $errorCode,
        public readonly array $context = [],
    ) {
        parent::__construct($statusCode, $message);
    }

    public static function itemUnavailable(): self
    {
        return new self(404, 'The activity item is unavailable.', 'activity_item_unavailable');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => [
                'code' => $this->errorCode,
                'context' => $this->context,
            ],
        ], $this->getStatusCode());
    }
}
