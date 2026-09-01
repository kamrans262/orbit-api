<?php

declare(strict_types=1);

namespace App\Modules\Circles\Actions;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Modules\Circles\Exceptions\CircleException;

final class UpdateCircleAction
{
    /**
     * @param  array{name?: string, description?: string|null}  $data
     */
    public function handle(Circle $circle, CircleMember $requesterMembership, array $data): Circle
    {
        if (! $requesterMembership->role->canManageMembers()) {
            throw CircleException::forbidden();
        }

        $circle->fill($data)->save();

        return $circle->refresh();
    }
}
