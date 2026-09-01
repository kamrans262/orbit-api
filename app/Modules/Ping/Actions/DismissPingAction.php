<?php

declare(strict_types=1);

namespace App\Modules\Ping\Actions;

use App\Models\Ping;
use App\Models\User;
use App\Modules\Ping\Enums\PingStatus;
use App\Modules\Ping\Exceptions\PingException;
use Illuminate\Support\Facades\DB;

final class DismissPingAction
{
    public function handle(User $user, string $pingId): Ping
    {
        $result = DB::transaction(function () use ($user, $pingId): array {
            $ping = Ping::query()
                ->with('recipientMembership')
                ->whereKey($pingId)
                ->lockForUpdate()
                ->first();

            if ($ping === null) {
                return ['exception' => PingException::notFound()];
            }

            if ($ping->recipientMembership->user_id !== $user->id) {
                return ['exception' => PingException::forbidden()];
            }

            if ($ping->status !== PingStatus::Pending) {
                return ['exception' => PingException::notPending()];
            }

            if ($ping->expires_at->isPast()) {
                $ping->forceFill(['status' => PingStatus::Expired])->save();

                return ['exception' => PingException::expired()];
            }

            $ping->forceFill([
                'status' => PingStatus::Dismissed,
                'dismissed_at' => now(),
            ])->save();

            return ['ping' => $ping];
        });

        if (isset($result['exception'])) {
            throw $result['exception'];
        }

        /** @var Ping $ping */
        $ping = $result['ping'];

        return $ping->load([
            'circle',
            'senderMembership.user',
            'recipientMembership.user',
        ]);
    }
}
