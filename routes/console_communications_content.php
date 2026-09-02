<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('orbit:communications:process-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('orbit:communications:dispatch-provider-deliveries')->everyMinute()->withoutOverlapping();
Schedule::command('orbit:maintenance:close-expired')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('orbit:content:publish-scheduled')->everyMinute()->withoutOverlapping();
