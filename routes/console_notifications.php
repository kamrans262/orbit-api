<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('orbit:notifications:import-sos')->everyMinute()->withoutOverlapping();
Schedule::command('orbit:notifications:purge-old')->dailyAt('03:30')->withoutOverlapping();
