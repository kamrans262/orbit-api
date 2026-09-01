<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Modules\Admin\Exceptions\AdminOperationException;
use App\Modules\Admin\Services\AdminInvitationService;
use App\Modules\Admin\Services\AdminRbacService;
use Illuminate\Console\Command;

final class InviteBootstrapAdminCommand extends Command
{
    protected $signature = 'orbit:admin:bootstrap {email} {--name=} {--role=super-administrator} {--force}';

    protected $description = 'Create the initial Orbit administrator invitation. Refuses once administrators exist unless --force is explicitly used.';

    public function handle(AdminRbacService $rbac, AdminInvitationService $invitations): int
    {
        $rbac->syncDefaults();
        $email = strtolower(trim((string) $this->argument('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Supply a valid administrator email address.');

            return self::FAILURE;
        }

        if (AdminUser::query()->exists() && ! $this->option('force')) {
            $this->error('Administrator records already exist. Use the protected Admin API for normal invitations.');

            return self::FAILURE;
        }

        try {
            $result = $invitations->invite(
                $email,
                $this->option('name') ? (string) $this->option('name') : null,
                [(string) $this->option('role')],
                null,
                null,
                'initial_bootstrap',
            );
        } catch (AdminOperationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $url = rtrim((string) config('orbit_admin.console_url'), '/').'/activate?token='.urlencode($result['rawToken']);
        $this->warn('Bootstrap invitation token (shown once):');
        $this->line($result['rawToken']);
        $this->line('Activation URL: '.$url);
        $this->line('An invitation email was also sent using the configured Laravel mail transport.');

        return self::SUCCESS;
    }
}
