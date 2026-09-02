<?php

declare(strict_types=1);

namespace App\Modules\System\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AttachApiRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = trim((string) $request->header('X-Request-Id', ''));
        $requestId = preg_match('/^[A-Za-z0-9._:-]{8,80}$/', $incoming)
            ? $incoming
            : (string) Str::uuid7();

        $request->headers->set('X-Request-Id', $requestId);
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('admin_request_id', $request->attributes->get('admin_request_id') ?? $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
