<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\OrbitDemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class SeedOrbitDemoDataCommand extends Command
{
    protected $signature = 'orbit:demo:seed';

    protected $description = 'Seed idempotent local/testing demo data for the Orbit Admin Console.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refusing to seed Orbit demo data outside local/testing.');

            return self::FAILURE;
        }

        $exit = Artisan::call('db:seed', ['--class' => OrbitDemoDataSeeder::class, '--force' => true]);
        $output = trim(Artisan::output());
        if ($output !== '') {
            $this->line($output);
        }
        if ($exit !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Orbit demo data seeded successfully.');
        $this->line('Admin password: '.OrbitDemoDataSeeder::ADMIN_PASSWORD);
        $this->line('Run `php artisan orbit:demo:credentials` for role emails and the current MFA code.');

        return self::SUCCESS;
    }
}
