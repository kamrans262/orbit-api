<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Admin\Services\AdminTotpService;
use Database\Seeders\OrbitDemoDataSeeder;
use Illuminate\Console\Command;

final class OrbitDemoCredentialsCommand extends Command
{
    protected $signature = 'orbit:demo:credentials {role? : Optional administrator role slug}';

    protected $description = 'Show local Orbit demo administrator credentials and the current TOTP code.';

    public function handle(AdminTotpService $totp): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo credentials are available only in local/testing.');

            return self::FAILURE;
        }

        $requested = $this->argument('role');
        $roles = OrbitDemoDataSeeder::ADMIN_ROLES;

        if (is_string($requested) && $requested !== '') {
            if (! array_key_exists($requested, $roles)) {
                $this->error('Unknown demo administrator role: '.$requested);

                return self::FAILURE;
            }
            $roles = [$requested => $roles[$requested]];
        }

        $this->warn('LOCAL/TESTING DEMO CREDENTIALS — never use these values in production.');
        $this->line('Password: '.OrbitDemoDataSeeder::ADMIN_PASSWORD);
        $this->line('Shared TOTP secret: '.OrbitDemoDataSeeder::TOTP_SECRET);
        $this->line('Current MFA code: '.$totp->currentCode(OrbitDemoDataSeeder::TOTP_SECRET));
        $this->newLine();

        $rows = [];
        foreach ($roles as $slug => $name) {
            $rows[] = [$slug, $name, $slug.'@'.OrbitDemoDataSeeder::ADMIN_DOMAIN];
        }
        $this->table(['Role', 'Name', 'Email'], $rows);

        return self::SUCCESS;
    }
}
