<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Exceptions;

use RuntimeException;

final class ModerationDomainException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
