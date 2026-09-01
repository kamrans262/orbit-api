<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('orbit:admin:sos:purge-expired-exports')
    ->hourly()
    ->withoutOverlapping();
