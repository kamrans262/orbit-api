<?php

declare(strict_types=1);

namespace App\Modules\Circles\Enums;

enum CircleRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Restricted = 'restricted';

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
