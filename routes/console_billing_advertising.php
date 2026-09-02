<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('orbit:billing:expire-subscriptions')->hourlyAt(35)->withoutOverlapping();
