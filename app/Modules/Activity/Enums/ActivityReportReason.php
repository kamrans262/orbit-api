<?php

declare(strict_types=1);

namespace App\Modules\Activity\Enums;

enum ActivityReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Safety = 'safety';
    case Other = 'other';
}
