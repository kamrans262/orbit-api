<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

use RuntimeException;

final class EmailOtpException extends RuntimeException
{
    public function __construct(
        public readonly string $apiCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
