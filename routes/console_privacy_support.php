<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('orbit:privacy:sync-identity-requests')->hourlyAt(10);
Schedule::command('orbit:privacy:purge-expired-exports')->hourlyAt(20);
