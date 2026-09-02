<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Exceptions;

use RuntimeException;

final class BillingAdvertisingDomainException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
