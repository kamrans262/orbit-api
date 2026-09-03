<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InjectAdminUiM2FoundationBridge
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->is('admin', 'admin/*')) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '' || str_contains($content, 'orbit-admin-m2-foundation-bridge.js')) {
            return $response;
        }

        $script = '<script src="/orbit-admin-m2-foundation-bridge.js?v=20260903-1"></script>';

        if (stripos($content, '<head>') !== false) {
            $content = preg_replace('/<head>/i', '<head>'.$script, $content, 1) ?? $content;
        } elseif (stripos($content, '</body>') !== false) {
            $content = preg_replace('/<\/body>/i', $script.'</body>', $content, 1) ?? $content;
        } else {
            return $response;
        }

        $response->setContent($content);
        $response->headers->remove('Content-Length');

        return $response;
    }
}
