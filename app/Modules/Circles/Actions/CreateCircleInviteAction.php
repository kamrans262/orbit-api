<?php

declare(strict_types=1);

namespace App\Modules\Circles\Actions;

use App\Models\Circle;
use App\Models\CircleInvite;
use App\Models\CircleMember;
use App\Models\User;
use App\Modules\Circles\Exceptions\CircleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class CreateCircleInviteAction
{
    /**
     * @param  array{expires_in_minutes?: int, max_uses?: int}  $data
     * @return array{invite: CircleInvite, code: string}
     */
    public function handle(
        User $user,
        Circle $circle,
        CircleMember $requesterMembership,
        array $data,
    ): array {
        if (! $requesterMembership->role->canManageMembers()) {
            throw CircleException::forbidden();
        }

        if ($circle->isArchived() || $circle->isExpired()) {
            throw CircleException::archived();
        }

        $expiresAt = now()->addMinutes($data['expires_in_minutes'] ?? 1440);

        if ($circle->expires_at !== null && $expiresAt->greaterThan($circle->expires_at)) {
            $expiresAt = Carbon::instance($circle->expires_at);
        }

        do {
            $code = Str::upper(Str::random(10));
            $codeHash = self::hashCode($code);
        } while (CircleInvite::query()->where('code_hash', $codeHash)->exists());

        $invite = CircleInvite::query()->create([
            'circle_id' => $circle->id,
            'created_by' => $user->id,
            'code_hash' => $codeHash,
            'max_uses' => $data['max_uses'] ?? 10,
            'uses_count' => 0,
            'expires_at' => $expiresAt,
        ]);

        return [
            'invite' => $invite,
            'code' => $code,
        ];
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', Str::upper(trim($code)));
    }
}
