<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

enum AdminStatus: string
{
    case Invited = 'invited';
    case MfaSetup = 'mfa_setup';
    case Active = 'active';
    case Disabled = 'disabled';
}
