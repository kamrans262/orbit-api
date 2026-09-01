<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\SecurityAuditLog;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function write(
        string $action,
        ?int $userId = null,
        ?int $actorUserId = null,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
        ?Request $request = null,
    ): SecurityAuditLog {
        $key = (string) config('app.key', 'orbit');

        return SecurityAuditLog::query()->create([
            'user_id' => $userId,
            'actor_user_id' => $actorUserId ?? $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_hash' => $request?->ip() ? hash_hmac('sha256', (string) $request->ip(), $key) : null,
            'user_agent_hash' => $request?->userAgent() ? hash_hmac('sha256', (string) $request->userAgent(), $key) : null,
            'metadata' => $this->sanitize($metadata),
            'occurred_at' => now(),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        $blocked = [
            'token', 'access_token', 'refresh_token', 'authorization', 'code', 'otp',
            'password', 'plaintext', 'content', 'ciphertext', 'private_key',
        ];

        return collect($metadata)
            ->reject(fn (mixed $value, string $key): bool => in_array(strtolower($key), $blocked, true))
            ->map(function (mixed $value): mixed {
                if (is_array($value)) {
                    return $this->sanitize($value);
                }

                if (is_string($value) && strlen($value) > 500) {
                    return substr($value, 0, 500);
                }

                return $value;
            })
            ->all();
    }
}
