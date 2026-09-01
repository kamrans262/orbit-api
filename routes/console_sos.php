<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('orbit:sos:escalate')->everyMinute()->withoutOverlapping();
Schedule::command('orbit:sos:purge-expired-recordings')->dailyAt('03:15')->withoutOverlapping();
