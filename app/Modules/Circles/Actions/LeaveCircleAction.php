<?php

declare(strict_types=1);

namespace App\Modules\Circles\Actions;

use App\Models\CircleMember;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Exceptions\CircleException;

final class LeaveCircleAction
{
    public function handle(CircleMember $membership): void
    {
        if ($membership->role === CircleRole::Owner) {
            throw CircleException::ownerCannotLeave();
        }

        $membership->delete();
    }
}
