<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class AdminSessionService
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    /** @return array{token:string, session:AdminSession} */
    public function issue(AdminUser $admin, Request $request): array
    {
        $sessionId = (string) Str::uuid7();
        $expiresAt = now()->addMinutes(max(15, (int) config('orbit_admin.session_lifetime_minutes', 480)));
        $idleExpiresAt = now()->addMinutes(max(5, (int) config('orbit_admin.idle_timeout_minutes', 15)));
        $newToken = $admin->createToken('admin-session:'.$sessionId, ['admin'], $expiresAt);

        $session = AdminSession::query()->create([
            'id' => $sessionId,
            'admin_user_id' => $admin->id,
            'access_token_id' => $newToken->accessToken->getKey(),
            'ip_hash' => $this->audit->hashIp($request->ip()),
            'user_agent_hash' => $this->audit->hashUserAgent($request->userAgent()),
            'last_seen_at' => now(),
            'idle_expires_at' => $idleExpiresAt,
            'expires_at' => $expiresAt,
            'reauthenticated_at' => now(),
            'mfa_verified_at' => now(),
        ]);

        return ['token' => $newToken->plainTextToken, 'session' => $session];
    }

    public function revoke(AdminSession $session, string $reason): void
    {
        if ($session->revoked_at !== null) {
            return;
        }

        if ($session->access_token_id !== null) {
            PersonalAccessToken::query()->whereKey($session->access_token_id)->delete();
        }

        $session->forceFill([
            'revoked_at' => now(),
            'revoke_reason' => mb_substr($reason, 0, 120),
        ])->save();
    }

    public function revokeAll(AdminUser $admin, string $reason, ?string $exceptSessionId = null): int
    {
        $query = AdminSession::query()->where('admin_user_id', $admin->id)->whereNull('revoked_at');
        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $sessions = $query->get();
        foreach ($sessions as $session) {
            $this->revoke($session, $reason);
        }

        return $sessions->count();
    }
}
