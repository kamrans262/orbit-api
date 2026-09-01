<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Models\AdminAuditLog;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\Request;

final class AdminAuditLogger
{
    public function write(
        string $action,
        ?AdminUser $admin = null,
        ?AdminSession $session = null,
        ?string $targetType = null,
        string|int|null $targetId = null,
        string $result = 'success',
        ?string $reason = null,
        array $before = [],
        array $after = [],
        array $metadata = [],
        ?Request $request = null,
    ): AdminAuditLog {
        $key = (string) config('app.key', 'orbit');

        return AdminAuditLog::query()->create([
            'admin_user_id' => $admin?->id,
            'admin_session_id' => $session?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId !== null ? (string) $targetId : null,
            'result' => $result,
            'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
            'request_id' => $request ? AdminApiResponse::requestId($request) : null,
            'ip_hash' => $request?->ip() ? hash_hmac('sha256', (string) $request->ip(), $key) : null,
            'user_agent_hash' => $request?->userAgent() ? hash_hmac('sha256', (string) $request->userAgent(), $key) : null,
            'before_state' => $this->sanitize($before),
            'after_state' => $this->sanitize($after),
            'metadata' => $this->sanitize($metadata),
            'occurred_at' => now(),
        ]);
    }

    public function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key', 'orbit'));
    }

    public function hashUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return hash_hmac('sha256', $userAgent, (string) config('app.key', 'orbit'));
    }

    private function sanitize(array $value): array
    {
        $blocked = [
            'password', 'password_confirmation', 'token', 'access_token', 'refresh_token',
            'authorization', 'otp', 'code', 'totp_secret', 'recovery_code', 'private_key',
            'plaintext', 'ciphertext', 'encrypted_key',
        ];

        return collect($value)
            ->reject(fn (mixed $item, string|int $key): bool => is_string($key) && in_array(strtolower($key), $blocked, true))
            ->map(function (mixed $item): mixed {
                if (is_array($item)) {
                    return $this->sanitize($item);
                }

                if (is_string($item) && mb_strlen($item) > 500) {
                    return mb_substr($item, 0, 500);
                }

                return $item;
            })
            ->all();
    }
}
