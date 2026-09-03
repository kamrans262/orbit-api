<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('routes moderation and support through one canonical administrator transport without private token readers', function (): void {
    $base = resource_path('js/admin-console');
    $moderation = file_get_contents($base.'/moderation-m4.js');
    $support = file_get_contents($base.'/support-m5.js');
    $client = file_get_contents($base.'/admin-api-client.js');
    $contract = file_get_contents($base.'/admin-auth.generated.js');

    expect($moderation)->toContain("import { adminApiRequest } from './admin-api-client.js';")
        ->and($moderation)->not->toContain('function resolveToken(')
        ->and($moderation)->not->toMatch('/(?:sessionStorage|localStorage)\\.getItem\\(/')
        ->and($support)->toContain("import { adminApiRequest } from './admin-api-client.js';")
        ->and($support)->not->toContain('function resolveToken(')
        ->and($support)->not->toMatch('/(?:sessionStorage|localStorage)\\.getItem\\(/')
        ->and($client)->toContain('adminAuthContract.strategy')
        ->and($client)->not->toContain('Object.keys(window.localStorage)')
        ->and($client)->not->toContain('Object.keys(window.sessionStorage)')
        ->and($contract)->toContain('sourceRoots')
        ->and($contract)->toContain('graphFiles');
});

it('keeps real protected moderation and support administrator routes registered', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes());

    expect($routes->contains(fn ($route): bool => str_starts_with(ltrim($route->uri(), '/'), 'api/admin/v1/moderation')))->toBeTrue()
        ->and($routes->contains(fn ($route): bool => str_starts_with(ltrim($route->uri(), '/'), 'api/admin/v1/support')))->toBeTrue()
        ->and($routes->contains(fn ($route): bool => ltrim($route->uri(), '/') === 'api/admin/v1/auth/reauthenticate'))->toBeTrue();
});
