<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminSavedReport;
use App\Models\AdminUser;
use App\Modules\Admin\AnalyticsOperations\Services\ReportService;
use Illuminate\Console\Command;

final class RunScheduledAdminReportsCommand extends Command
{
    protected $signature = 'orbit:analytics:run-scheduled-reports';

    protected $description = 'Generate due scheduled administrator analytics exports.';

    public function handle(ReportService $s): int
    {
        foreach (AdminSavedReport::query()->whereNotNull('schedule')->where('next_run_at', '<=', now())->get() as $r) {
            $a = AdminUser::query()->find($r->admin_user_id);
            if (! $a) {
                continue;
            }$s->export($a, $r);
            $next = match ($r->schedule) {
                'weekly' => now()->addWeek(),'monthly' => now()->addMonth(),default => now()->addDay()
            };
            $r->forceFill(['next_run_at' => $next])->save();
        }

return self::SUCCESS;
    }
}
