<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditSecurityMutation
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $uri = ltrim((string) $request->route()?->uri(), '/');
        $method = strtoupper($request->method());
        $userId = $request->user()?->getKey();

        $action = $this->resolveAction($uri, $method);
        if ($action === null) {
            return $response;
        }

        $routeParameters = $request->route()?->parameters() ?? [];
        $targetId = null;
        foreach (['deviceId', 'circleId', 'memberId', 'id'] as $key) {
            if (isset($routeParameters[$key]) && is_scalar($routeParameters[$key])) {
                $targetId = (string) $routeParameters[$key];
                break;
            }
        }

        $metadata = [
            'route' => $request->route()?->getName(),
            'method' => $method,
        ];

        if ($action === 'identity.sign_in' && $userId === null) {
            $identity = (string) ($request->input('email') ?? $request->input('phone') ?? '');
            if ($identity !== '') {
                $metadata['identity_hash'] = hash('sha256', strtolower(trim($identity)));
            }
        }

        $this->audit->write(
            $action,
            $userId !== null ? (int) $userId : null,
            targetType: str_contains($uri, 'devices') ? 'device' : (str_contains($uri, 'circles') ? 'circle' : 'auth'),
            targetId: $targetId,
            metadata: $metadata,
            request: $request,
        );

        return $response;
    }

    private function resolveAction(string $uri, string $method): ?string
    {
        if (str_contains($uri, 'auth/email-otp/verify') || str_contains($uri, 'auth/otp/verify')) {
            return 'identity.sign_in';
        }

        if (str_contains($uri, 'devices')) {
            return $method === 'DELETE' ? 'identity.device.revoked' : 'identity.device.registered_or_updated';
        }

        if (str_contains($uri, 'circles')) {
            if (str_contains($uri, 'privacy') || str_contains($uri, 'permissions')) {
                return 'identity.circle.permission_changed';
            }

            if (str_contains($uri, 'join') || $method === 'DELETE') {
                return 'identity.circle.membership_changed';
            }
        }

        return null;
    }
}
