<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IntegrationStatus;
use Illuminate\Console\Command;

final class SyncOperationalIntegrationsCommand extends Command
{
    protected $signature = 'orbit:operations:sync-integrations';

    protected $description = 'Synchronize the non-secret Orbit integration operations catalog.';

    public function handle(): int
    {
        $items = [
            ['service' => 'email', 'provider' => (string) config('mail.default', 'unknown'), 'enabled' => ! in_array((string) config('mail.default'), ['log', 'array'], true), 'public_config' => ['driver' => (string) config('mail.default', 'unknown')]],
            ['service' => 'object_storage', 'provider' => (string) config('filesystems.default', 'unknown'), 'enabled' => true, 'public_config' => ['disk' => (string) config('filesystems.default', 'unknown')]],
            ['service' => 'websocket', 'provider' => 'reverb', 'enabled' => config('reverb.default') !== null, 'public_config' => ['server' => (string) config('reverb.default', 'reverb')]],
            ['service' => 'push', 'provider' => 'provider_neutral', 'enabled' => true, 'public_config' => ['delivery_boundary' => 'notification_deliveries']],
            ['service' => 'sms', 'provider' => 'unconfigured', 'enabled' => false, 'public_config' => []],
            ['service' => 'payments', 'provider' => 'provider_neutral', 'enabled' => true, 'public_config' => ['ledger' => 'payment_transactions']],
        ];

        foreach ($items as $item) {
            IntegrationStatus::query()->updateOrCreate(
                ['service' => $item['service'], 'provider' => $item['provider'], 'environment' => 'production'],
                ['enabled' => $item['enabled'], 'health' => 'unknown', 'public_config' => $item['public_config']],
            );
        }

        $this->info('Orbit operational integration catalog synchronized.');

        return self::SUCCESS;
    }
}
