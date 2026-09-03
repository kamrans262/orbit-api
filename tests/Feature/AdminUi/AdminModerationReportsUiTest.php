<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

test('it registers moderation reports appeals and risk inside the canonical admin console', function (): void {
    expect(Route::has('admin.console.operations.moderation.index'))->toBeTrue()
        ->and(Route::has('admin.console.operations.moderation.reports.show'))->toBeTrue()
        ->and(Route::has('admin.console.operations.moderation.appeals.index'))->toBeTrue()
        ->and(Route::has('admin.console.operations.moderation.appeals.show'))->toBeTrue()
        ->and(Route::has('admin.console.operations.moderation.risk.index'))->toBeTrue()
        ->and(Route::has('admin.console.operations.moderation.risk.show'))->toBeTrue();
});

test('it does not inherit SOS specific permission or reauthentication middleware', function (): void {
    $route = Route::getRoutes()->getByName('admin.console.operations.moderation.index');
    $middleware = strtolower(implode('|', $route?->gatherMiddleware() ?? []));

    expect($middleware)->not->toContain('sos.')
        ->and($middleware)->not->toContain('ensureadminpermission')
        ->and($middleware)->not->toContain('admin.permission:')
        ->and($middleware)->not->toContain('requirerecentadminreauthentication');
});

test('it keeps moderation evidence privacy safe and cancellation controls non submitting', function (): void {
    $report = file_get_contents(resource_path('views/admin/console/operations/moderation/reports/show.blade.php'));
    $runtime = file_get_contents(resource_path('js/admin-console/moderation-m4.js'));

    expect($report)->toContain('reporter explicitly submitted')
        ->and($report)->toContain('No universal plaintext access')
        ->and($runtime)->toContain("close.type = 'button'")
        ->and($runtime)->toContain("cancel.type = 'button'")
        ->and($runtime)->toContain('form.reportValidity()')
        ->and($runtime)->not->toContain('.innerHTML');
});

test('it connects moderation navigation to the canonical sidebar instead of a second shell', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    $sidebar = null;

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        if (str_contains($source, 'orbit-nav-item') && str_contains($source, 'admin.console.operations.moderation.index')) {
            $sidebar = $file->getPathname();
            break;
        }
    }

    expect($sidebar)->not->toBeNull();
});
