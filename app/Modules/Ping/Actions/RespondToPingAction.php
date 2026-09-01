<?php

declare(strict_types=1);

namespace App\Modules\Ping\Actions;

use App\Models\Ping;
use App\Models\User;
use App\Modules\Ping\Enums\PingResponseType;
use App\Modules\Ping\Enums\PingStatus;
use App\Modules\Ping\Events\PingResponded;
use App\Modules\Ping\Exceptions\PingException;
use Illuminate\Support\Facades\DB;

final class RespondToPingAction
{
    public function handle(User $user, string $pingId, PingResponseType $responseType): Ping
    {
        $result = DB::transaction(function () use ($user, $pingId, $responseType): array {
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
                'status' => PingStatus::Responded,
                'response_type' => $responseType,
                'responded_at' => now(),
            ])->save();

            return ['ping' => $ping];
        });

        if (isset($result['exception'])) {
            throw $result['exception'];
        }

        /** @var Ping $ping */
        $ping = $result['ping'];

        $ping->load([
            'circle',
            'senderMembership.user',
            'recipientMembership.user',
        ]);

        PingResponded::dispatch($ping);

        return $ping;
    }
}
