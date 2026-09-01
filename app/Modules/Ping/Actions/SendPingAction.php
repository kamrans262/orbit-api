<?php

declare(strict_types=1);

namespace App\Modules\Ping\Actions;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Ping;
use App\Models\User;
use App\Modules\Ping\Enums\PingStatus;
use App\Modules\Ping\Events\PingSent;
use App\Modules\Ping\Exceptions\PingException;
use Illuminate\Support\Facades\DB;

final class SendPingAction
{
    public function handle(User $sender, string $circleId, string $recipientMembershipId): Ping
    {
        $ping = DB::transaction(function () use ($sender, $circleId, $recipientMembershipId): Ping {
            $circle = Circle::query()
                ->available()
                ->whereKey($circleId)
                ->lockForUpdate()
                ->first();

            if ($circle === null) {
                throw PingException::circleNotFound();
            }

            $senderMembership = CircleMember::query()
                ->where('circle_id', $circle->id)
                ->where('user_id', $sender->id)
                ->first();

            if ($senderMembership === null) {
                throw PingException::circleNotFound();
            }

            $recipientMembership = CircleMember::query()
                ->whereKey($recipientMembershipId)
                ->where('circle_id', $circle->id)
                ->first();

            if ($recipientMembership === null) {
                throw PingException::recipientNotFound();
            }

            if ($recipientMembership->user_id === $sender->id) {
                throw PingException::selfPingNotAllowed();
            }

            if (! $recipientMembership->can_ping) {
                throw PingException::recipientDisabledPings();
            }

            $cooldownSeconds = max(1, (int) config('orbit.ping.cooldown_seconds', 10));

            $recentPingExists = Ping::query()
                ->where('sender_membership_id', $senderMembership->id)
                ->where('recipient_membership_id', $recipientMembership->id)
                ->where('created_at', '>=', now()->subSeconds($cooldownSeconds))
                ->exists();

            if ($recentPingExists) {
                throw PingException::cooldown();
            }

            return Ping::query()->create([
                'circle_id' => $circle->id,
                'sender_membership_id' => $senderMembership->id,
                'recipient_membership_id' => $recipientMembership->id,
                'status' => PingStatus::Pending,
                'expires_at' => now()->addSeconds(
                    max(30, (int) config('orbit.ping.ttl_seconds', 120)),
                ),
            ]);
        });

        $ping->load([
            'circle',
            'senderMembership.user',
            'recipientMembership.user',
        ]);

        PingSent::dispatch($ping);

        return $ping;
    }
}
