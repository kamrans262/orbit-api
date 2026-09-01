<?php

declare(strict_types=1);

namespace App\Modules\Circles\Actions;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Exceptions\CircleException;

final class ArchiveCircleAction
{
    public function handle(Circle $circle, CircleMember $requesterMembership): Circle
    {
        if ($requesterMembership->role !== CircleRole::Owner) {
            throw CircleException::forbidden();
        }

        if ($circle->archived_at === null) {
            $circle->forceFill(['archived_at' => now()])->save();
        }

        return $circle->refresh();
    }
}
