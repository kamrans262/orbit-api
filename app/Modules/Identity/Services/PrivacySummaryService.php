<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PrivacySummaryService
{
    public function forUser(User $user): array
    {
        $summary = [
            'global_ghost_mode' => (bool) ($user->global_ghost_mode ?? false),
            'read_receipts_enabled' => null,
            'notification_preferences' => null,
            'circles' => [],
            'account_deletion' => null,
            'data_export' => null,
        ];

        if (Schema::hasTable('messaging_preferences')) {
            $preference = DB::table('messaging_preferences')->where('user_id', $user->getKey())->first();
            if ($preference && property_exists($preference, 'read_receipts_enabled')) {
                $summary['read_receipts_enabled'] = (bool) $preference->read_receipts_enabled;
            }
        }

        if (Schema::hasTable('notification_preferences')) {
            $preference = DB::table('notification_preferences')->where('user_id', $user->getKey())->first();
            if ($preference) {
                $summary['notification_preferences'] = [
                    'push_enabled' => (bool) ($preference->push_enabled ?? true),
                    'in_app_enabled' => (bool) ($preference->in_app_enabled ?? true),
                    'quiet_hours_enabled' => (bool) ($preference->quiet_hours_enabled ?? false),
                ];
            }
        }

        if (Schema::hasTable('circle_members')) {
            $columns = array_flip(Schema::getColumnListing('circle_members'));
            $select = array_values(array_intersect(
                ['circle_id', 'role', 'location_mode', 'location_fidelity', 'can_view_moments', 'moment_access', 'can_ping', 'ping_permission', 'message_permission'],
                array_keys($columns),
            ));

            $summary['circles'] = DB::table('circle_members')
                ->where('user_id', $user->getKey())
                ->get($select === [] ? ['circle_id'] : $select)
                ->map(fn (object $row): array => (array) $row)
                ->values()
                ->all();
        }

        if (Schema::hasTable('account_deletion_requests')) {
            $deletion = DB::table('account_deletion_requests')
                ->where('user_id', $user->getKey())
                ->latest('requested_at')
                ->first();

            if ($deletion) {
                $summary['account_deletion'] = [
                    'status' => $deletion->status,
                    'scheduled_for' => $deletion->scheduled_for,
                    'blocking_reason' => $deletion->blocking_reason,
                ];
            }
        }

        if (Schema::hasTable('data_export_requests')) {
            $export = DB::table('data_export_requests')
                ->where('user_id', $user->getKey())
                ->latest('requested_at')
                ->first();

            if ($export) {
                $summary['data_export'] = [
                    'id' => $export->id,
                    'status' => $export->status,
                    'expires_at' => $export->expires_at,
                ];
            }
        }

        return $summary;
    }
}
