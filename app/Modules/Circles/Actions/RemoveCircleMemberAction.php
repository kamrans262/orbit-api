<?php

declare(strict_types=1);

namespace App\Modules\Circles\Actions;

use App\Models\CircleMember;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Exceptions\CircleException;

final class RemoveCircleMemberAction
{
    public function handle(CircleMember $requesterMembership, CircleMember $targetMembership): void
    {
        if ($targetMembership->role === CircleRole::Owner) {
            throw CircleException::ownerCannotBeRemoved();
        }

        if ($requesterMembership->id === $targetMembership->id) {
            throw CircleException::forbidden();
        }

        if (! $requesterMembership->role->canManageMembers()) {
            throw CircleException::forbidden();
        }

        if ($requesterMembership->role === CircleRole::Admin && $targetMembership->role === CircleRole::Admin) {
            throw CircleException::forbidden();
        }

        $targetMembership->delete();
    }
}
