<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('orbit:admin:purge-expired-security-artifacts')->dailyAt('04:30')->withoutOverlapping();
