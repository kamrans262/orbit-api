<?php

declare(strict_types=1);

namespace App\Modules\Admin\Exceptions;

use RuntimeException;

final class AdminOperationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }
}
