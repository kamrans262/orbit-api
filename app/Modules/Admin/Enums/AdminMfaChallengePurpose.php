<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

enum AdminMfaChallengePurpose: string
{
    case Login = 'login';
    case Setup = 'setup';
}
