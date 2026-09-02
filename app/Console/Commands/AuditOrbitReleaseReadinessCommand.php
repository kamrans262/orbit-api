<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Admin\BackendCompletion\Services\ReleaseReadinessService;
use Illuminate\Console\Command;

final class AuditOrbitReleaseReadinessCommand extends Command
{
    protected $signature = 'orbit:release:audit {--json : Emit machine-readable JSON}';

    protected $description = 'Audit Orbit backend release-readiness and security-sensitive production configuration.';

    public function handle(ReleaseReadinessService $service): int
    {
        $result = $service->audit();

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Orbit backend release-readiness audit');
            foreach ($result['checks'] as $check) {
                $prefix = $check['status'] === 'pass' ? '[PASS]' : ($check['severity'] === 'blocking' ? '[FAIL]' : '[WARN]');
                $this->line($prefix.' '.$check['key'].' — '.$check['message']);
            }
            $this->newLine();
            $this->line('Environment: '.$result['environment']);
            $this->line('Blocking failures: '.$result['blocking_failures']);
            $this->line('Warnings: '.$result['warnings']);
        }

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
