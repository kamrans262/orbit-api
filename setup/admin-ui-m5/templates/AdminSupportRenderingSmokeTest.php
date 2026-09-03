<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->withoutMiddleware();
});

test('all M5 support web views render through the canonical layout', function (): void {
    $routes = [
        ['admin.console.operations.support.index', []],
        ['admin.console.operations.support.show', ['ticketId' => 'support-smoke-ticket-1']],
    ];

    foreach ($routes as [$name, $parameters]) {
        expect(Route::has($name))->toBeTrue("Missing route: {$name}");

        $response = $this->get(route($name, $parameters));
        $response->assertOk();
        $response->assertDontSee('__ORBIT_LAYOUT__', false);
        $response->assertDontSee('__ORBIT_SECTION__', false);
    }
});

test('no installed M5 support blade view contains installer placeholders', function (): void {
    $root = resource_path('views/admin/console/operations/support');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $count = 0;

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $count++;
        $source = (string) file_get_contents($file->getPathname());
        expect($source)->not->toContain('__ORBIT_LAYOUT__')
            ->and($source)->not->toContain('__ORBIT_SECTION__');
    }

    expect($count)->toBe(2);
});
