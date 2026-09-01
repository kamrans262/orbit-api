<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('orbit:identity:finalize-deletions')->dailyAt('03:45')->withoutOverlapping();
Schedule::command('orbit:identity:purge-audit-logs')->dailyAt('04:15')->withoutOverlapping();
