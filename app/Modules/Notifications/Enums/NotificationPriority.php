<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

enum NotificationPriority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Highest = 'highest';
}
