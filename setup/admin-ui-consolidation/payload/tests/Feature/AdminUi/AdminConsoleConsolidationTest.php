<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->withoutVite();
});

it('renders every canonical M1 and M2 administration shell without embedding operational data', function (): void {
    $this->get('/admin')
        ->assertOk()
        ->assertSee('data-orbit-canonical-shell="v1"', false)
        ->assertSee('data-orbit-auth-owner="foundation"', false)
        ->assertSee('data-orbit-view="dashboard"', false)
        ->assertDontSee('data-admin-auth-dialog', false)
        ->assertDontSee('id="orbit-auth-title"', false);

    $this->get('/admin/operations/users')
        ->assertOk()
        ->assertSee('data-orbit-canonical-shell="v1"', false)
        ->assertSee('data-orbit-view="users-index"', false)
        ->assertDontSee('data-admin-auth-dialog', false);

    $this->get('/admin/operations/users/1')
        ->assertOk()
        ->assertSee('data-orbit-view="user-show"', false)
        ->assertDontSee('push_token', false)
        ->assertDontSee('private_key', false);

    $this->get('/admin/operations/circles')
        ->assertOk()
        ->assertSee('data-orbit-view="circles-index"', false);

    $this->get('/admin/operations/circles/example-circle')
        ->assertOk()
        ->assertSee('data-orbit-view="circle-show"', false)
        ->assertDontSee('ciphertext', false);
});

it('delegates administrator authentication to the existing Foundation login and MFA flow', function (): void {
    $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
    $shell = file_get_contents(resource_path('js/admin-console/shell.js'));
    $session = file_get_contents(resource_path('js/admin-console/auth-session.js'));
    $client = file_get_contents(resource_path('js/admin-console/api-client.js'));

    expect($layout)->toContain('data-orbit-auth-owner="foundation"')
        ->and($layout)->toContain('data-auth-gate')
        ->and($layout)->not->toContain('data-admin-auth-dialog')
        ->and($layout)->not->toContain('data-admin-auth-form')
        ->and($shell)->toContain("const LOGIN_PATH = '/admin/login';")
        ->and($shell)->toContain("adminApi('/api/admin/v1/auth/me')")
        ->and($shell)->not->toContain('/api/admin/v1/auth/login')
        ->and($shell)->not->toContain('/api/admin/v1/auth/mfa/verify')
        ->and($shell)->not->toContain('writeAdminSession')
        ->and($session)->toContain('FOUNDATION_TOKEN_KEYS')
        ->and($session)->toContain('FOUNDATION_DETECTED_TOKEN_KEYS')
        ->and($session)->not->toContain('window.fetch')
        ->and($session)->not->toContain('XMLHttpRequest')
        ->and($client)->toContain("if (token) headers.set('Authorization', `Bearer \${token}`);")
        ->and($client)->not->toContain("throw new OrbitAdminApiError('Administrator authentication is required.'");
});

it('pins the dashboard UI to the completed M9 data snapshot contract and fails visibly on drift', function (): void {
    $dashboard = file_get_contents(resource_path('js/admin-console/dashboard.js'));

    expect($dashboard)->toContain('data?.snapshot')
        ->and($dashboard)->toContain('business.users.total')
        ->and($dashboard)->toContain('dashboard_contract_mismatch')
        ->and($dashboard)->toContain('resolveDashboardValue')
        ->and($dashboard)->not->toContain('return data.summary ?? data.dashboard ?? data;');
});

it('uses real canonical navigation for users and circles without the old next-module interception', function (): void {
    $response = $this->get('/admin')->assertOk();

    $response
        ->assertSee('href="'.route('admin.console.operations.users.index').'"', false)
        ->assertSee('href="'.route('admin.console.operations.circles.index').'"', false)
        ->assertDontSee('Users UI is next')
        ->assertDontSee('data-orbit-m2-destination', false);
});

it('registers one canonical route family for the consolidated admin console', function (): void {
    expect(Route::has('admin.console.dashboard'))->toBeTrue()
        ->and(Route::has('admin.console.operations.users.index'))->toBeTrue()
        ->and(Route::has('admin.console.operations.users.show'))->toBeTrue()
        ->and(Route::has('admin.console.operations.circles.index'))->toBeTrue()
        ->and(Route::has('admin.console.operations.circles.show'))->toBeTrue();
});

it('keeps the canonical assets and removes the obsolete separate M2 architecture', function (): void {
    expect(is_file(base_path('resources/css/admin-console.css')))->toBeTrue()
        ->and(is_file(base_path('resources/js/admin-console/index.js')))->toBeTrue()
        ->and(is_file(base_path('resources/js/admin-console/foundation-auth-keys.js')))->toBeTrue()
        ->and(is_file(base_path('resources/views/admin/layouts/app.blade.php')))->toBeTrue()
        ->and(is_file(base_path('routes/admin_console.php')))->toBeTrue()
        ->and(is_file(base_path('resources/views/admin/operations/layouts/shell.blade.php')))->toBeFalse()
        ->and(is_file(base_path('resources/css/admin-ui-m2.css')))->toBeFalse()
        ->and(is_file(base_path('routes/admin_ui_m2.php')))->toBeFalse()
        ->and(is_file(base_path('app/Http/Middleware/InjectAdminUiM2FoundationBridge.php')))->toBeFalse()
        ->and(is_file(base_path('public/orbit-admin-m2-foundation-bridge.js')))->toBeFalse();
});
