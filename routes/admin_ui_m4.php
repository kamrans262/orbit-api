<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

$canonicalRoute = Route::getRoutes()->getByName('admin.console.operations.users.index')
    ?? Route::getRoutes()->getByName('admin.console.operations.sos.index');

$middleware = array_values(array_filter(
    $canonicalRoute?->gatherMiddleware() ?? ['web'],
    static function (mixed $middleware): bool {
        if (! is_string($middleware)) {
            return true;
        }

        $normalized = strtolower($middleware);

        return ! str_contains($normalized, 'ensureadminpermission')
            && ! str_contains($normalized, 'admin.permission:')
            && ! str_contains($normalized, 'permission:')
            && ! str_contains($normalized, 'requirerecentadminreauthentication')
            && ! str_contains($normalized, 'reauth')
            && ! str_contains($normalized, 'auditadminmutation');
    },
));

if ($middleware === []) {
    $middleware = ['web'];
}

Route::middleware($middleware)
    ->prefix('admin/operations/moderation')
    ->name('admin.console.operations.moderation.')
    ->group(function (): void {
        Route::view('/', 'admin.console.operations.moderation.index')->name('index');
        Route::get('/reports/{reportId}', fn (string $reportId) => view('admin.console.operations.moderation.reports.show', compact('reportId')))->name('reports.show');
        Route::view('/appeals', 'admin.console.operations.moderation.appeals.index')->name('appeals.index');
        Route::get('/appeals/{appealId}', fn (string $appealId) => view('admin.console.operations.moderation.appeals.show', compact('appealId')))->name('appeals.show');
        Route::view('/risk', 'admin.console.operations.moderation.risk.index')->name('risk.index');
        Route::get('/risk/{profileId}', fn (string $profileId) => view('admin.console.operations.moderation.risk.show', compact('profileId')))->name('risk.show');
    });
