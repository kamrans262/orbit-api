<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Exceptions;

use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class AnalyticsOperationsException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return AdminApiResponse::error($request, $this->getMessage(), $this->errorCode, $this->status);
    }
}
