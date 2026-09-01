<?php

declare(strict_types=1);

namespace App\Modules\Auth\Support;

use Illuminate\Support\Str;

final class EmailNormalizer
{
    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
