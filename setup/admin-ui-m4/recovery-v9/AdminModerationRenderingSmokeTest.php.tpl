<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->withoutMiddleware();
});

test('all M4 moderation web views render through the canonical layout', function (): void {
    $id = '00000000-0000-4000-8000-000000000001';

    $routes = [
        ['admin.console.operations.moderation.index', []],
        ['admin.console.operations.moderation.reports.show', ['reportId' => $id]],
        ['admin.console.operations.moderation.appeals.index', []],
        ['admin.console.operations.moderation.appeals.show', ['appealId' => $id]],
        ['admin.console.operations.moderation.risk.index', []],
        ['admin.console.operations.moderation.risk.show', ['profileId' => $id]],
    ];

    foreach ($routes as [$name, $parameters]) {
        expect(Route::has($name))->toBeTrue("Missing route: {$name}");

        $response = $this->get(route($name, $parameters));
        $response->assertOk();
        $response->assertDontSee('__ORBIT_LAYOUT__', false);
        $response->assertDontSee('__ORBIT_SECTION__', false);
    }
});

test('no installed M4 moderation blade view contains installer placeholders', function (): void {
    $root = resource_path('views/admin/console/operations/moderation');
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

    expect($count)->toBe(6);
});
