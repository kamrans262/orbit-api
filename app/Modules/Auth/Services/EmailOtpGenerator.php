<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

class EmailOtpGenerator
{
    public function generate(): string
    {
        return (string) random_int(100000, 999999);
    }
}
