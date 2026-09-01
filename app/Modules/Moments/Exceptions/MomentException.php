<?php

declare(strict_types=1);

namespace App\Modules\Moments\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MomentException extends RuntimeException
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
        return new self('Circle not found.', 'MOMENT_CIRCLE_NOT_FOUND', 404);
    }

    public static function viewingDisabled(): self
    {
        return new self('Moments are hidden for this Circle membership.', 'MOMENT_VIEWING_DISABLED', 403);
    }

    public static function publishingRestricted(): self
    {
        return new self('This Circle role cannot publish Moments.', 'MOMENT_PUBLISHING_RESTRICTED', 403);
    }

    public static function invalidMedia(): self
    {
        return new self('The encrypted media asset cannot be used for this Moment.', 'MOMENT_INVALID_MEDIA', 422);
    }

    public static function notFound(): self
    {
        return new self('Moment not found.', 'MOMENT_NOT_FOUND', 404);
    }

    public static function expired(): self
    {
        return new self('Moment has expired.', 'MOMENT_EXPIRED', 410);
    }

    public static function forbidden(): self
    {
        return new self('You do not have permission to manage this Moment.', 'MOMENT_FORBIDDEN', 403);
    }

    public static function idConflict(): self
    {
        return new self('The Moment ID is already in use.', 'MOMENT_ID_CONFLICT', 409);
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
