<?php

declare(strict_types=1);

namespace App\Modules\Moments\Enums;

enum MomentStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Deleted = 'deleted';
}
