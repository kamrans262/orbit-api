<?php

declare(strict_types=1);

namespace App\Modules\Moments\Actions;

use App\Models\Moment;
use App\Models\User;
use App\Modules\Moments\Enums\MomentStatus;
use App\Modules\Moments\Events\MomentDeleted;
use App\Modules\Moments\Exceptions\MomentException;

final class DeleteMomentAction
{
    public function handle(User $user, string $momentId): Moment
    {
        $moment = Moment::query()->whereKey($momentId)->first();

        if ($moment === null || $moment->status === MomentStatus::Deleted) {
            throw MomentException::notFound();
        }

        if ($moment->author_user_id !== $user->id) {
            throw MomentException::forbidden();
        }

        $moment->forceFill([
            'status' => MomentStatus::Deleted,
            'deleted_at' => now(),
        ])->save();

        MomentDeleted::dispatch($moment);

        return $moment;
    }
}
