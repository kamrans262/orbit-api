<?php

declare(strict_types=1);

namespace App\Modules\Profile\Actions;

use App\Models\User;

final class UpdateProfileAction
{
    /**
     * @param  array{name?: string|null, timezone?: string, locale?: string}  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->fill($data);
        $user->save();

        return $user->refresh();
    }
}
