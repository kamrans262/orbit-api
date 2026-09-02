<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Services;

use App\Models\ActivityEvent;
use App\Models\Circle;
use App\Models\Message;
use App\Models\Moment;
use App\Models\Ping;
use App\Models\SosEvent;
use App\Models\User;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use Illuminate\Support\Facades\DB;

final class ModerationReportAccessService
{
    /** @return array{target_user_id:?int,snapshot:array<string,mixed>} */
    public function resolve(User $reporter, string $type, string $id): array
    {
        return match ($type) {
            'user' => $this->user($reporter, $id),
            'circle' => $this->circle($reporter, $id),
            'message' => $this->message($reporter, $id),
            'moment' => $this->moment($reporter, $id),
            'ping' => $this->ping($reporter, $id),
            'sos' => $this->sos($reporter, $id),
            'activity' => $this->activity($reporter, $id),
            default => throw new ModerationDomainException('REPORT_TARGET_INVALID', 'Unsupported report target.', 422),
        };
    }

    private function user(User $reporter, string $id): array
    {
        if (! ctype_digit($id) || (int) $id === (int) $reporter->id) {
            throw new ModerationDomainException('REPORT_TARGET_UNAVAILABLE', 'The report target is unavailable.', 404);
        }
        $target = User::query()->find((int) $id);
        if (! $target || ! $this->sharesCircle((int) $reporter->id, (int) $target->id)) {
            throw new ModerationDomainException('REPORT_TARGET_UNAVAILABLE', 'The report target is unavailable.', 404);
        }

        return ['target_user_id' => (int) $target->id, 'snapshot' => [
            'type' => 'user', 'id' => (int) $target->id, 'name' => $target->name,
        ]];
    }

    private function circle(User $reporter, string $id): array
    {
        $circle = Circle::query()->find($id);
        if (! $circle || ! $this->isCircleMember((int) $reporter->id, (string) $circle->id)) {
            throw new ModerationDomainException('REPORT_TARGET_UNAVAILABLE', 'The report target is unavailable.', 404);
        }

        return ['target_user_id' => null, 'snapshot' => [
            'type' => 'circle', 'id' => (string) $circle->id, 'name' => $circle->name, 'owner_user_id' => (int) $circle->created_by,
        ]];
    }

    private function message(User $reporter, string $id): array
    {
        $message = Message::query()->find($id);
        if (! $message || ! $this->isCircleMember((int) $reporter->id, (string) $message->circle_id)) {
            throw new ModerationDomainException('REPORT_TARGET_UNAVAILABLE', 'The report target is unavailable.', 404);
        }

        return ['target_user_id' => (int) $message->sender_user_id, 'snapshot' => [
            'type' => 'message', 'id' => (string) $message->id, 'circle_id' => (string) $message->circle_id,
            'sender_user_id' => (int) $message->sender_user_id, 'message_type' => $this->scalar($message->type),
            'created_at' => $message->created_at?->toIso8601String(),
            'privacy' => 'metadata_only',
        ]];
    }

    private function moment(User $reporter, string $id): array
    {
        $moment = Moment::query()->find($id);
        if (! $moment || ! $this->isCircleMember((int) $reporter->id, (string) $moment->circle_id)) {
            throw new ModerationDomainException('REPORT_TARGET_UNAVAILABLE', 'The report target is unavailable.', 404);
        }

        return ['target_user_id' => (int) $moment->author_user_id, 'snapshot' => [
            'type' => 'moment', 'id' => (string) $moment->id, 'circle_id' => (string) $moment->circle_id,
            'author_user_id' => (int) $moment->author_user_id, 'status' => $this->scalar($moment->status),
            'created_at' => $moment->created_at?->toIso8601String(), 'privacy' => 'encrypted_media_metadata_only',
        ]];
    }

    private function ping(User $reporter, string $id): array
    {
        $ping = Ping::query()->with(['senderMembership', 'recipientMembership'])->find($id);
        if (! $ping) {
            throw new ModerationDomainException('REPORT_TARGET_UNAVAILABLE', 'The report target is unavailable.', 404);
        }
        $sender = (int) $ping->senderMembership?->user_id;
        $recipient = (int) $ping->recipientMembership?->user_id;
        $reporterId = (int) $reporter->id;
        if (! in_array($reporterId, [$sender, $recipient], true)) {
            throw new ModerationDomainException('REPORT_TARGET_UNAVAILABLE', 'The report target is unavailable.', 404);
        }
        $targetUser = $reporterId === $sender ? $recipient : $sender;

        return ['target_user_id' => $targetUser ?: null, 'snapshot' => [
            'type' => 'ping', 'id' => (string) $ping->id, 'circle_id' => (string) $ping->circle_id,
            'sender_user_id' => $sender, 'recipient_user_id' => $recipient,
            'status' => $this->scalar($ping->status), 'created_at' => $ping->created_at?->toIso8601String(),
        ]];
    }

    private function sos(User $reporter, string $id): array
    {
        $sos = SosEvent::query()->find($id);
        if (! $sos || ! $this->isCircleMember((int) $reporter->id, (string) $sos->circle_id)) {
            throw new ModerationDomainException('REPORT_TARGET_UNAVAILABLE', 'The report target is unavailable.', 404);
        }

        return ['target_user_id' => (int) $sos->user_id, 'snapshot' => [
            'type' => 'sos', 'id' => (string) $sos->id, 'circle_id' => (string) $sos->circle_id,
            'originator_user_id' => (int) $sos->user_id, 'status' => (string) $sos->status,
            'escalation_stage' => (int) $sos->escalation_stage, 'activated_at' => $sos->activated_at?->toIso8601String(),
            'privacy' => 'no_location_or_recording',
        ]];
    }

    private function activity(User $reporter, string $id): array
    {
        $event = ActivityEvent::query()->find($id);
        if (! $event || ! $this->isCircleMember((int) $reporter->id, (string) $event->circle_id)) {
            throw new ModerationDomainException('REPORT_TARGET_UNAVAILABLE', 'The report target is unavailable.', 404);
        }

        return ['target_user_id' => $event->actor_user_id ? (int) $event->actor_user_id : null, 'snapshot' => [
            'type' => 'activity', 'id' => (string) $event->id, 'circle_id' => (string) $event->circle_id,
            'actor_user_id' => $event->actor_user_id ? (int) $event->actor_user_id : null,
            'event_type' => (string) $event->event_type, 'source_type' => (string) $event->source_type,
            'source_id' => $event->source_id, 'occurred_at' => $event->occurred_at?->toIso8601String(),
            'privacy' => 'safe_activity_metadata_only',
        ]];
    }

    private function scalar(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    private function isCircleMember(int $userId, string $circleId): bool
    {
        return DB::table('circle_members')->where('circle_id', $circleId)->where('user_id', $userId)->exists();
    }

    private function sharesCircle(int $one, int $two): bool
    {
        $oneCircles = DB::table('circle_members')->where('user_id', $one)->pluck('circle_id');
        if ($oneCircles->isEmpty()) {
            return false;
        }

        return DB::table('circle_members')->where('user_id', $two)->whereIn('circle_id', $oneCircles)->exists();
    }
}
