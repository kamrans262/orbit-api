<?php

declare(strict_types=1);

namespace App\Modules\Circles\Actions;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\CircleType;
use App\Modules\Circles\Enums\LocationMode;
use Illuminate\Support\Facades\DB;

final class CreateCircleAction
{
    /**
     * @param  array{name: string, description?: string|null, type?: string, expires_at?: string|null}  $data
     * @return array{circle: Circle, membership: CircleMember}
     */
    public function handle(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $circle = Circle::query()->create([
                'created_by' => $user->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? CircleType::Standard->value,
                'expires_at' => $data['expires_at'] ?? null,
            ]);

            $membership = CircleMember::query()->create([
                'circle_id' => $circle->id,
                'user_id' => $user->id,
                'role' => CircleRole::Owner,
                'location_mode' => LocationMode::Hidden,
                'joined_at' => now(),
            ]);

            return [
                'circle' => $circle,
                'membership' => $membership,
            ];
        });
    }
}
