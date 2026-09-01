<?php

declare(strict_types=1);

namespace App\Modules\Circles\Actions;

use App\Models\CircleMember;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Exceptions\CircleException;

final class UpdateCircleMemberAction
{
    /**
     * @param array{
     *     role?: string,
     *     location_mode?: string,
     *     can_ping?: bool,
     *     can_message?: bool,
     *     can_view_moments?: bool,
     *     activity_visibility?: bool
     * } $data
     */
    public function handle(
        CircleMember $requesterMembership,
        CircleMember $targetMembership,
        array $data,
    ): CircleMember {
        $roleData = array_intersect_key($data, ['role' => true]);
        $privacyData = array_intersect_key($data, [
            'location_mode' => true,
            'can_ping' => true,
            'can_message' => true,
            'can_view_moments' => true,
            'activity_visibility' => true,
        ]);

        if ($privacyData !== []) {
            if ($requesterMembership->id !== $targetMembership->id) {
                throw CircleException::privacyBelongsToMember();
            }

            $targetMembership->fill($privacyData);
        }

        if ($roleData !== []) {
            $this->applyRoleChange($requesterMembership, $targetMembership, $roleData['role']);
        }

        $targetMembership->save();

        return $targetMembership->refresh();
    }

    private function applyRoleChange(
        CircleMember $requesterMembership,
        CircleMember $targetMembership,
        string $newRole,
    ): void {
        if ($requesterMembership->id === $targetMembership->id) {
            throw CircleException::invalidRoleChange();
        }

        if (! $requesterMembership->role->canManageMembers()) {
            throw CircleException::forbidden();
        }

        if ($targetMembership->role === CircleRole::Owner) {
            throw CircleException::invalidRoleChange();
        }

        $newRoleEnum = CircleRole::from($newRole);

        if ($newRoleEnum === CircleRole::Owner) {
            throw CircleException::invalidRoleChange();
        }

        if ($requesterMembership->role === CircleRole::Admin) {
            if ($targetMembership->role === CircleRole::Admin || $newRoleEnum === CircleRole::Admin) {
                throw CircleException::forbidden();
            }
        }

        $targetMembership->role = $newRoleEnum;
    }
}
