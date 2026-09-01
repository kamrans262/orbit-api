<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Services;

use App\Models\AdminCircleControl;
use App\Models\AdminRecordNote;
use App\Models\AdminRecordTag;
use App\Models\Circle;
use Illuminate\Support\Facades\DB;

final readonly class AdminCircleOperationsPresenter
{
    public function __construct(private AdminCircleOperationsService $controls) {}

    /** @return array<string,mixed> */
    public function summary(Circle $circle): array
    {
        $control = $circle->relationLoaded('adminOperationalControl')
            ? $circle->getRelation('adminOperationalControl')
            : AdminCircleControl::query()->whereKey($circle->id)->first();

        $owner = null;
        if ($circle->relationLoaded('memberships')) {
            $ownerMembership = $circle->memberships->first();
            if ($ownerMembership?->relationLoaded('user') && $ownerMembership->user !== null) {
                $owner = (object) [
                    'id' => $ownerMembership->user->id,
                    'name' => $ownerMembership->user->name,
                    'email' => $ownerMembership->user->email,
                ];
            }
        }
        $owner ??= DB::table('circle_members')->join('users', 'users.id', '=', 'circle_members.user_id')
            ->where('circle_members.circle_id', $circle->id)->where('circle_members.role', 'owner')
            ->first(['users.id', 'users.name', 'users.email']);

        return [
            'id' => $circle->id,
            'name' => $circle->name,
            'description' => $circle->description,
            'type' => $circle->type->value,
            'owner' => $owner ? ['id' => $owner->id, 'display_name' => $owner->name, 'email' => $owner->email] : null,
            'member_count' => $this->countAttributeOrQuery($circle, 'admin_member_count', 'circle_members'),
            'created_at' => $circle->created_at?->toIso8601String(),
            'expires_at' => $circle->expires_at?->toIso8601String(),
            'archived_at' => $circle->archived_at?->toIso8601String(),
            'operational_status' => in_array($control?->status, ['frozen', 'removed'], true) ? $control->status : ($circle->archived_at !== null ? 'archived' : 'normal'),
            'activity_count' => $this->countAttributeOrQuery($circle, 'admin_activity_count', 'activity_events'),
            'sos_count' => $this->countAttributeOrQuery($circle, 'admin_sos_count', 'sos_events'),
            'active_sos_count' => $this->activeSosCount($circle),
            'report_count' => $this->reportCount($circle),
            'safety_flagged' => $this->safetyFlagged($circle),
        ];
    }

    /** @return array<string,mixed> */
    public function detail(Circle $circle): array
    {
        $control = AdminCircleControl::query()->whereKey($circle->id)->first();
        $members = DB::table('circle_members')->join('users', 'users.id', '=', 'circle_members.user_id')
            ->where('circle_members.circle_id', $circle->id)->orderBy('circle_members.role')->orderBy('users.id')
            ->get([
                'circle_members.id as membership_id', 'users.id as user_id', 'users.name', 'users.email',
                'circle_members.role', 'circle_members.location_mode', 'circle_members.can_ping',
                'circle_members.can_message', 'circle_members.can_view_moments', 'circle_members.activity_visibility',
                'circle_members.joined_at',
            ])->map(fn ($row): array => [
                'membership_id' => $row->membership_id, 'user_id' => $row->user_id,
                'display_name' => $row->name, 'email' => $row->email, 'role' => $row->role,
                'location_mode' => $row->location_mode, 'can_ping' => (bool) $row->can_ping,
                'can_message' => (bool) $row->can_message, 'can_view_moments' => (bool) $row->can_view_moments,
                'activity_visibility' => (bool) $row->activity_visibility, 'joined_at' => $row->joined_at,
            ])->all();

        return [
            ...$this->summary($circle),
            'controls' => $control ? $this->controls->snapshot($control) : [
                'status' => 'normal', 'feature_restrictions' => [], 'reason' => null,
                'frozen_at' => null, 'removed_at' => null,
            ],
            'members' => $members,
            'recent_activity' => DB::table('activity_events')->where('circle_id', $circle->id)->latest('occurred_at')->limit(20)
                ->get(['id', 'actor_user_id', 'event_type', 'source_type', 'source_id', 'occurred_at', 'removed_at'])->map(fn ($row): array => (array) $row)->all(),
            'sos_routing' => [
                'total_incidents' => (int) DB::table('sos_events')->where('circle_id', $circle->id)->count(),
                'active_incidents' => (int) DB::table('sos_events')->where('circle_id', $circle->id)->where('status', 'active')->count(),
                'latest_incident_at' => DB::table('sos_events')->where('circle_id', $circle->id)->max('activated_at'),
            ],
            'notes' => AdminRecordNote::query()->where('target_type', 'circle')->where('target_id', $circle->id)->latest('created_at')->limit(100)->get()->map(fn ($note): array => [
                'id' => $note->id, 'note' => $note->note, 'admin_user_id' => $note->admin_user_id,
                'created_at' => $note->created_at?->toIso8601String(),
            ])->all(),
            'tags' => AdminRecordTag::query()->where('target_type', 'circle')->where('target_id', $circle->id)->orderBy('tag')->get()->map(fn ($tag): array => [
                'id' => $tag->id, 'tag' => $tag->tag, 'admin_user_id' => $tag->admin_user_id,
                'created_at' => $tag->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function countAttributeOrQuery(Circle $circle, string $attribute, string $table): int
    {
        $attributes = $circle->getAttributes();
        if (array_key_exists($attribute, $attributes)) {
            return (int) $attributes[$attribute];
        }

        return (int) DB::table($table)->where('circle_id', $circle->id)->count();
    }

    private function activeSosCount(Circle $circle): int
    {
        $attributes = $circle->getAttributes();
        if (array_key_exists('admin_active_sos_count', $attributes)) {
            return (int) $attributes['admin_active_sos_count'];
        }

        return (int) DB::table('sos_events')->where('circle_id', $circle->id)->where('status', 'active')->count();
    }

    private function reportCount(Circle $circle): int
    {
        $attributes = $circle->getAttributes();
        if (array_key_exists('admin_report_count', $attributes)) {
            return (int) $attributes['admin_report_count'];
        }

        return (int) DB::table('activity_reports')
            ->join('activity_events', 'activity_events.id', '=', 'activity_reports.activity_event_id')
            ->where('activity_events.circle_id', $circle->id)
            ->count();
    }

    private function safetyFlagged(Circle $circle): bool
    {
        $attributes = $circle->getAttributes();
        if (array_key_exists('admin_safety_tag_count', $attributes)) {
            return (int) $attributes['admin_safety_tag_count'] > 0;
        }

        return DB::table('admin_record_tags')
            ->where('target_type', 'circle')
            ->where('target_id', $circle->id)
            ->whereIn('tag', ['Safety Concern', 'Abuse Watch', 'Legal Hold'])
            ->exists();
    }
}
