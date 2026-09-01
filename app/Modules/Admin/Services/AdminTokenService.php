<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

final class AdminTokenService
{
    public function generate(int $bytes = 48): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
