<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

test('it registers support management inside the canonical admin console', function (): void {
    expect(Route::has('admin.console.operations.support.index'))->toBeTrue()
        ->and(Route::has('admin.console.operations.support.show'))->toBeTrue();
});

test('it inherits only the canonical admin web shell middleware', function (): void {
    $route = Route::getRoutes()->getByName('admin.console.operations.support.index');
    $middleware = strtolower(implode('|', $route?->gatherMiddleware() ?? []));

    expect($middleware)->not->toContain('sos.')
        ->and($middleware)->not->toContain('ensureadminpermission')
        ->and($middleware)->not->toContain('admin.permission:')
        ->and($middleware)->not->toContain('requirerecentadminreauthentication');
});

test('it keeps internal support notes private and dialog cancellation non submitting', function (): void {
    $show = file_get_contents(resource_path('views/admin/console/operations/support/show.blade.php'));
    $runtime = file_get_contents(resource_path('js/admin-console/support-m5.js'));

    expect($show)->toContain('never consumer-visible')
        ->and($show)->toContain('Privacy-safe')
        ->and($runtime)->toContain("close.type = 'button'")
        ->and($runtime)->toContain("cancel.type = 'button'")
        ->and($runtime)->toContain('form.reportValidity()')
        ->and($runtime)->not->toContain('.innerHTML')
        ->and($runtime)->not->toContain('document.write')
        ->and($runtime)->not->toContain('eval(');
});

test('it adds support navigation without replacing moderation or the canonical shell', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    $sidebar = null;

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        if (str_contains($source, 'orbit-nav-item') && str_contains($source, 'admin.console.operations.support.index')) {
            $sidebar = $source;
            break;
        }
    }

    expect($sidebar)->not->toBeNull()
        ->and($sidebar)->toContain('admin.console.operations.moderation.index')
        ->and($sidebar)->toContain('Safety / SOS')
        ->and($sidebar)->toContain('<span>Users</span>')
        ->and($sidebar)->toContain('<span>Circles</span>');
});

test('it keeps M5 isolated in its own source assets', function (): void {
    $entry = (string) file_get_contents(resource_path('js/admin-console/index.js'));
    $runtime = (string) file_get_contents(resource_path('js/admin-console/support-m5.js'));

    expect(substr_count($entry, "import './support-m5.js';"))->toBe(1)
        ->and($runtime)->toContain('[data-orbit-view^="support-"]')
        ->and($runtime)->toContain("import '../../css/admin-console-m5.css'")
        ->and(file_exists(resource_path('js/admin-console/moderation-m4.js')))->toBeTrue();
});
