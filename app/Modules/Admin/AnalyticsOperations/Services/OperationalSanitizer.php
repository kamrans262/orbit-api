<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Services;

use App\Modules\Admin\AnalyticsOperations\Exceptions\AnalyticsOperationsException;

final class OperationalSanitizer
{
    private const array BLOCKED = ['password', 'secret', 'token', 'authorization', 'credential', 'private_key', 'api_key', 'access_key', 'refresh_token', 'ciphertext', 'plaintext', 'recording_ref', 'latitude', 'longitude'];

    public function rejectSecretKey(string $key): void
    {
        $normalized = strtolower($key);
        foreach (self::BLOCKED as $fragment) {
            if (str_contains($normalized, $fragment)) {
                throw new AnalyticsOperationsException('OPERATIONS_SECRET_KEY_REJECTED', 'Secret-shaped configuration keys are not permitted in administrator runtime configuration.', 422);
            }
        }
    }

    public function sanitize(array $value): array
    {
        $out = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $blocked = false;
                foreach (self::BLOCKED as $fragment) {
                    if (str_contains(strtolower($key), $fragment)) {
                        $blocked = true;
                        break;
                    }
                }
                if ($blocked) {
                    continue;
                }
            }
            $out[$key] = is_array($item) ? $this->sanitize($item) : (is_string($item) && mb_strlen($item) > 500 ? mb_substr($item, 0, 500) : $item);
        }

        return $out;
    }
}
