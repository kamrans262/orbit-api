<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Schedule;

Schedule::command('orbit:operations:process-queue-actions')->everyMinute()->withoutOverlapping();
Schedule::command('orbit:analytics:run-scheduled-reports')->hourly()->withoutOverlapping();
Schedule::command('orbit:operations:scan-alerts')->everyFiveMinutes()->withoutOverlapping();
