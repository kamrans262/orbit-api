<?php

declare(strict_types=1);

use Database\Seeders\OrbitDemoDataSeeder;

it('uses a dedicated demo email domain and non-production credential namespace', function (): void {
    expect(OrbitDemoDataSeeder::ADMIN_DOMAIN)->toBe('admin.demo.orbit.test')
        ->and(OrbitDemoDataSeeder::USER_DOMAIN)->toBe('demo.orbit.test')
        ->and(OrbitDemoDataSeeder::ADMIN_PASSWORD)->toContain('Demo');
});
