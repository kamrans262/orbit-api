<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Models\AdminSession;
use App\Modules\Admin\Services\AdminSessionService;
use App\Modules\Admin\Support\AdminApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminSessionActive
{
    public function __construct(private readonly AdminSessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tokenId = $request->attributes->get('admin_access_token_id');
        $session = is_numeric($tokenId)
            ? AdminSession::query()->where('access_token_id', (int) $tokenId)->whereNull('revoked_at')->first()
            : null;

        if ($session === null) {
            return AdminApiResponse::error($request, 'The administrator session is unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        if ($session->expires_at->isPast() || $session->idle_expires_at->isPast()) {
            $this->sessions->revoke($session, $session->expires_at->isPast() ? 'absolute_timeout' : 'idle_timeout');

            return AdminApiResponse::error($request, 'The administrator session has expired.', 'ADMIN_SESSION_EXPIRED', 401);
        }

        $session->forceFill([
            'last_seen_at' => now(),
            'idle_expires_at' => now()->addMinutes(max(5, (int) config('orbit_admin.idle_timeout_minutes', 15))),
        ])->save();
        $request->attributes->set('admin_session', $session);

        return $next($request);
    }
}
