<?php

declare(strict_types=1);

namespace App\Modules\Sos\Enums;

enum SosResponderStatus: string
{
    case Pending = 'pending';
    case Engaged = 'engaged';
    case Declined = 'declined';
}
