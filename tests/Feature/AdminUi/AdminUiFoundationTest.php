<?php

declare(strict_types=1);

it('renders the administrator login page with strict UI security headers', function () {
    $response = $this->get('/admin/login');

    $response->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('admin-ui/css/admin.css', false)
        ->assertSee('admin-ui/js/pages/login.js', false)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect((string) $response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'");
});

it('renders the administrator MFA page without embedding privileged state', function () {
    $this->get('/admin/mfa')
        ->assertOk()
        ->assertSee('Verify it’s you')
        ->assertSee('admin-ui/js/pages/mfa.js', false)
        ->assertDontSee('access_token');
});

it('renders the responsive dashboard shell without server side business data', function () {
    $response = $this->get('/admin');

    $response->assertOk()
        ->assertSee('Operations overview')
        ->assertSee('Search Orbit administration', false)
        ->assertSee('resources/js/admin-console/index.js', false)
        ->assertDontSee('message_envelopes')
        ->assertDontSee('ciphertext');
});

it('keeps the dashboard alias available for predictable navigation', function () {
    $this->get('/admin/dashboard')->assertOk()->assertSee('Operations overview');
});
