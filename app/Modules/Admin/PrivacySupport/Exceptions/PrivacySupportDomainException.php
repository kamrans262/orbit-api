<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Exceptions;

use RuntimeException;

final class PrivacySupportDomainException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}
