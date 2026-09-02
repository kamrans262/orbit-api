<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\BillingPlan;
use App\Models\User;
use App\Modules\Admin\BillingAdvertising\Services\BillingCatalogService;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Services\AdminRbacService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class OrbitDemoDataSeeder extends Seeder
{
    public const string ADMIN_PASSWORD = 'OrbitDemo!2026';

    public const string TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public const string ADMIN_DOMAIN = 'admin.demo.orbit.test';

    public const string USER_DOMAIN = 'demo.orbit.test';

    public const array ADMIN_ROLES = [
        'super-administrator' => 'Alex Morgan',
        'platform-administrator' => 'Priya Shah',
        'safety-operator' => 'Omar Khan',
        'senior-safety-operator' => 'Maya Chen',
        'moderator' => 'Noah Williams',
        'support-agent' => 'Sofia Martinez',
        'finance-manager' => 'Daniel Brooks',
        'marketing-manager' => 'Amelia Parker',
        'advertising-manager' => 'Liam Carter',
        'analyst' => 'Ava Thompson',
        'security-administrator' => 'Ethan Reed',
        'devops-operator' => 'Zara Ahmed',
        'compliance-officer' => 'Grace Kim',
        'read-only' => 'Oliver Stone',
    ];

    /** @var array<string, AdminUser> */
    private array $admins = [];

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, string> */
    private array $circles = [];

    /** @var array<string, string> */
    private array $devices = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Orbit demo data is restricted to local/testing environments.');
        }

        $this->assertSchema();

        app(AdminRbacService::class)->syncDefaults();
        app(BillingCatalogService::class)->syncDefaults();
        Artisan::call('orbit:operations:sync-integrations');

        DB::transaction(function (): void {
            $this->seedAdministrators();
            $this->seedUsers();
            $this->seedCirclesAndMemberships();
            $this->seedDevicesAndPresence();
            $this->seedIdentitySessions();
            $this->seedOperationalUserStates();
            $this->seedEngagement();
            $this->seedSos();
            $this->seedModerationAndRisk();
            $this->seedPrivacyAndSupport();
            $this->seedBillingAndAdvertising();
            $this->seedCommunicationsAndContent();
            $this->seedOperationsAndAnalytics();
            $this->seedAuditAndNotifications();
        });
    }

    private function assertSchema(): void
    {
        foreach (['users', 'admin_users', 'admin_roles', 'circles', 'devices', 'sos_events', 'moderation_reports', 'support_tickets', 'billing_plans', 'system_incidents'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Missing required table [{$table}]. Run all Orbit migrations before seeding demo data.");
            }
        }
    }

    private function seedAdministrators(): void
    {
        foreach (self::ADMIN_ROLES as $slug => $name) {
            $role = AdminRole::query()->where('slug', $slug)->firstOrFail();
            $email = $slug.'@'.self::ADMIN_DOMAIN;
            $admin = AdminUser::query()->firstOrNew(['email' => $email]);
            $admin->forceFill([
                'name' => $name,
                'password' => self::ADMIN_PASSWORD,
                'status' => AdminStatus::Active,
                'totp_secret' => self::TOTP_SECRET,
                'mfa_confirmed_at' => now()->subDays(30),
                'activated_at' => now()->subDays(60),
                'failed_login_count' => 0,
                'locked_until' => null,
                'access_expires_at' => null,
                'disabled_at' => null,
                'last_login_at' => now()->subMinutes(array_search($slug, array_keys(self::ADMIN_ROLES), true) * 11 + 3),
            ])->save();
            $admin->roles()->sync([$role->id]);
            $this->admins[$slug] = $admin;
        }

        $stateAdmins = [
            ['key' => 'invited', 'name' => 'Invited Demo Admin', 'email' => 'invited@'.self::ADMIN_DOMAIN, 'status' => AdminStatus::Invited, 'mfa' => null, 'locked_until' => null, 'expires' => null, 'disabled_at' => null],
            ['key' => 'disabled', 'name' => 'Disabled Demo Admin', 'email' => 'disabled@'.self::ADMIN_DOMAIN, 'status' => AdminStatus::Disabled, 'mfa' => now()->subDays(5), 'locked_until' => null, 'expires' => null, 'disabled_at' => now()->subDay()],
            ['key' => 'locked', 'name' => 'Locked Demo Admin', 'email' => 'locked@'.self::ADMIN_DOMAIN, 'status' => AdminStatus::Active, 'mfa' => now()->subDays(5), 'locked_until' => now()->addMinutes(20), 'expires' => null, 'disabled_at' => null],
            ['key' => 'temporary', 'name' => 'Temporary Access Admin', 'email' => 'temporary@'.self::ADMIN_DOMAIN, 'status' => AdminStatus::Active, 'mfa' => now()->subDays(2), 'locked_until' => null, 'expires' => now()->addDays(3), 'disabled_at' => null],
        ];

        $readOnly = AdminRole::query()->where('slug', 'read-only')->firstOrFail();
        foreach ($stateAdmins as $row) {
            $admin = AdminUser::query()->firstOrNew(['email' => $row['email']]);
            $admin->forceFill([
                'name' => $row['name'],
                'password' => self::ADMIN_PASSWORD,
                'status' => $row['status'],
                'totp_secret' => $row['mfa'] ? self::TOTP_SECRET : null,
                'mfa_confirmed_at' => $row['mfa'],
                'activated_at' => $row['mfa'] ? now()->subDays(5) : null,
                'failed_login_count' => $row['key'] === 'locked' ? 5 : 0,
                'locked_until' => $row['locked_until'],
                'access_expires_at' => $row['expires'],
                'disabled_at' => $row['disabled_at'],
                'last_login_at' => $row['key'] === 'disabled' ? now()->subDays(4) : null,
            ])->save();
            $admin->roles()->sync([$readOnly->id]);
        }
    }

    private function seedUsers(): void
    {
        $personas = [
            'owner_plus' => ['Aisha Rahman', 'aisha', 'en', 'Asia/Karachi', false, 48],
            'free_online' => ['Hamza Siddiqui', 'hamza', 'en', 'Asia/Karachi', false, 20],
            'lite_active' => ['Sara Malik', 'sara', 'en', 'Asia/Karachi', false, 120],
            'plus_family' => ['Bilal Ahmed', 'bilal', 'en', 'Asia/Karachi', false, 240],
            'suspended_temp' => ['Mina Yusuf', 'mina', 'en', 'Asia/Dubai', false, 360],
            'suspended_indefinite' => ['Ryan Cole', 'ryan', 'en', 'America/New_York', false, 500],
            'reverification' => ['Hina Qureshi', 'hina', 'en', 'Asia/Karachi', false, 30],
            'restricted' => ['Jack Wilson', 'jack', 'en', 'Europe/London', false, 400],
            'high_risk' => ['Nadia Noor', 'nadia', 'en', 'Asia/Karachi', false, 800],
            'ghost' => ['Leo Martin', 'leo', 'en', 'Europe/Paris', true, 150],
            'unverified' => ['Fatima Zahra', 'fatima', 'ur', 'Asia/Karachi', false, 4],
            'deletion_pending' => ['Mason Lee', 'mason', 'en', 'Asia/Singapore', false, 700],
            'export_pending' => ['Emma Davis', 'emma', 'en', 'America/Los_Angeles', false, 420],
            'support_vip' => ['Yusuf Ali', 'yusuf', 'en', 'Asia/Karachi', false, 52],
            'reported' => ['Chloe Brown', 'chloe', 'en', 'Australia/Sydney', false, 900],
            'appeal_pending' => ['Adam Khan', 'adam', 'en', 'Asia/Karachi', false, 380],
            'new_today' => ['Zoya Mir', 'zoya', 'en', 'Asia/Karachi', false, 2],
            'dormant' => ['Nathan Scott', 'nathan', 'en', 'America/Chicago', false, 1500],
            'multi_device' => ['Lina Park', 'lina', 'en', 'Asia/Seoul', false, 190],
            'suspicious_device' => ['Ibrahim Tariq', 'ibrahim', 'en', 'Asia/Karachi', false, 270],
            'no_device' => ['Olivia Grant', 'olivia', 'en', 'Europe/London', false, 610],
            'privacy_case' => ['Arman Raza', 'arman', 'en', 'Asia/Karachi', false, 340],
            'ad_engaged' => ['Sophia Evans', 'sophia', 'en', 'America/Toronto', false, 85],
            'safety_responder' => ['Tariq Mahmood', 'tariq', 'en', 'Asia/Karachi', false, 1000],
            'creator' => ['Mia Johnson', 'mia', 'en', 'America/New_York', false, 310],
            'student' => ['Ayaan Sheikh', 'ayaan', 'en', 'Asia/Karachi', false, 45],
            'traveler' => ['Ella Moore', 'ella', 'en', 'Europe/Berlin', false, 650],
            'parent' => ['Khalid Hassan', 'khalid', 'en', 'Asia/Riyadh', false, 980],
            'night_shift' => ['Ruby Taylor', 'ruby', 'en', 'Europe/London', false, 210],
            'inactive_paid' => ['Theo Lewis', 'theo', 'en', 'America/Denver', false, 760],
        ];

        foreach ($personas as $key => [$name, $local, $locale, $timezone, $ghost, $hoursAgo]) {
            $user = User::query()->firstOrNew(['email' => $local.'@'.self::USER_DOMAIN]);
            $created = now()->subHours($hoursAgo);
            $user->forceFill([
                'name' => $name,
                'email_verified_at' => $key === 'unverified' ? null : $created->copy()->addMinutes(3),
                'password' => 'unused-demo-password',
                'timezone' => $timezone,
                'locale' => $locale,
                'global_ghost_mode' => $ghost,
                'created_at' => $created,
                'updated_at' => now()->subMinutes(min(1000, max(1, intdiv($hoursAgo, 2)))),
                'account_deletion_scheduled_for' => $key === 'deletion_pending' ? now()->addDays(21) : null,
                'account_deleted_at' => null,
            ])->save();
            $this->users[$key] = $user;
        }
    }

    private function seedCirclesAndMemberships(): void
    {
        $defs = [
            'family' => ['Rahman Family', 'Family safety and everyday coordination', 'standard', null, null, 'owner_plus'],
            'friends' => ['Weekend Crew', 'Friends planning and lightweight location sharing', 'standard', null, null, 'free_online'],
            'campus' => ['Campus Circle', 'Study group and campus safety', 'standard', null, null, 'student'],
            'travel' => ['Berlin Trip', 'Temporary travel Circle', 'temporary', now()->addDays(10), null, 'traveler'],
            'archived' => ['Old Neighbors', 'Archived historical Circle', 'standard', null, now()->subDays(12), 'parent'],
            'frozen' => ['Community Watch', 'Temporarily frozen by Trust & Safety', 'standard', null, null, 'safety_responder'],
            'work' => ['Night Shift', 'Late shift check-ins', 'standard', null, null, 'night_shift'],
            'removed' => ['Removed Test Circle', 'Removed operational demo record', 'standard', null, null, 'reported'],
        ];

        foreach ($defs as $key => [$name, $description, $type, $expires, $archived, $ownerKey]) {
            $id = $this->uuid('circle-'.$key);
            $this->circles[$key] = $id;
            $this->upsert('circles', ['id' => $id], [
                'created_by' => $this->users[$ownerKey]->id,
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'expires_at' => $expires,
                'archived_at' => $archived,
                'created_at' => now()->subDays(30 - array_search($key, array_keys($defs), true) * 3),
                'updated_at' => now()->subHours(4),
            ]);
        }

        $members = [
            'family' => ['owner_plus' => ['owner', 'precise'], 'plus_family' => ['admin', 'approximate'], 'parent' => ['member', 'precise'], 'student' => ['member', 'hidden'], 'safety_responder' => ['member', 'approximate']],
            'friends' => ['free_online' => ['owner', 'approximate'], 'lite_active' => ['member', 'precise'], 'ghost' => ['member', 'hidden'], 'creator' => ['admin', 'approximate'], 'ad_engaged' => ['member', 'hidden']],
            'campus' => ['student' => ['owner', 'precise'], 'new_today' => ['member', 'approximate'], 'privacy_case' => ['member', 'hidden'], 'support_vip' => ['admin', 'approximate']],
            'travel' => ['traveler' => ['owner', 'precise'], 'multi_device' => ['member', 'precise'], 'free_online' => ['member', 'approximate']],
            'archived' => ['parent' => ['owner', 'hidden'], 'dormant' => ['member', 'hidden']],
            'frozen' => ['safety_responder' => ['owner', 'precise'], 'high_risk' => ['member', 'approximate'], 'reported' => ['member', 'hidden']],
            'work' => ['night_shift' => ['owner', 'approximate'], 'restricted' => ['member', 'hidden'], 'reverification' => ['member', 'approximate']],
            'removed' => ['reported' => ['owner', 'hidden'], 'appeal_pending' => ['member', 'hidden']],
        ];

        foreach ($members as $circleKey => $rows) {
            foreach ($rows as $userKey => [$role, $locationMode]) {
                $id = $this->uuid("membership-{$circleKey}-{$userKey}");
                $this->upsert('circle_members', ['id' => $id], [
                    'circle_id' => $this->circles[$circleKey],
                    'user_id' => $this->users[$userKey]->id,
                    'role' => $role,
                    'location_mode' => $locationMode,
                    'can_ping' => $userKey !== 'restricted',
                    'can_message' => $userKey !== 'restricted',
                    'can_view_moments' => true,
                    'activity_visibility' => true,
                    'joined_at' => now()->subDays(25)->addHours(array_search($userKey, array_keys($rows), true) * 3),
                    'created_at' => now()->subDays(25),
                    'updated_at' => now()->subHours(6),
                ]);
            }
        }

        $this->upsert('admin_circle_controls', ['circle_id' => $this->circles['frozen']], [
            'status' => 'frozen', 'feature_restrictions' => $this->json(['messaging' => true]), 'reason' => 'Demo safety investigation', 'frozen_at' => now()->subHours(9), 'removed_at' => null,
            'updated_by_admin_id' => $this->admins['safety-operator']->id, 'created_at' => now()->subHours(9), 'updated_at' => now()->subHours(2),
        ]);
        $this->upsert('admin_circle_controls', ['circle_id' => $this->circles['removed']], [
            'status' => 'removed', 'feature_restrictions' => null, 'reason' => 'Demo abuse enforcement', 'frozen_at' => null, 'removed_at' => now()->subDays(2),
            'updated_by_admin_id' => $this->admins['moderator']->id, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
    }

    private function seedDevicesAndPresence(): void
    {
        $noDevice = ['no_device'];
        $index = 0;
        foreach ($this->users as $key => $user) {
            if (in_array($key, $noDevice, true)) {
                continue;
            }

            $platform = $index % 3 === 0 ? 'ios' : 'android';
            $id = $this->uuid('device-'.$key.'-primary');
            $this->devices[$key] = $id;
            $lastSeen = match ($key) {
                'dormant', 'inactive_paid' => now()->subDays(45),
                'suspended_indefinite' => now()->subDays(12),
                default => now()->subMinutes(($index * 13) % 700),
            };
            $this->upsert('devices', ['id' => $id], [
                'user_id' => $user->id,
                'client_device_id' => 'demo-'.$key.'-primary',
                'name' => ucfirst(str_replace('_', ' ', $key)).' Phone',
                'device_name' => ucfirst(str_replace('_', ' ', $key)).' Phone',
                'platform' => $platform,
                'app_version' => $index % 5 === 0 ? '2.2.0' : '2.6.0',
                'os_version' => $platform === 'ios' ? 'iOS 19.1' : 'Android 16',
                'public_identity_key' => 'demo-public-key-'.$key,
                'push_token' => 'demo-push-token-'.$key,
                'last_seen_at' => $lastSeen,
                'revoked_at' => $key === 'suspended_indefinite' ? now()->subDays(10) : null,
                'created_at' => $user->created_at->copy()->addHour(),
                'updated_at' => $lastSeen,
            ]);

            $country = str_contains($user->timezone, 'Karachi') ? 'PK' : (str_contains($user->timezone, 'London') ? 'GB' : 'US');
            $this->upsert('user_regional_profiles', ['user_id' => $user->id], [
                'country_code' => $country, 'platform' => $platform, 'app_version' => $index % 5 === 0 ? '2.2.0' : '2.6.0',
                'locale' => $user->locale, 'created_at' => $user->created_at, 'updated_at' => $lastSeen,
            ]);

            if (! in_array($key, ['dormant', 'inactive_paid', 'suspended_indefinite'], true)) {
                $this->upsert('presence_states', ['user_id' => $user->id], [
                    'device_id' => $id,
                    'status' => $key === 'ghost' ? 'away' : 'online',
                    'latitude' => $key === 'ghost' ? null : 24.8607 + ($index * 0.001),
                    'longitude' => $key === 'ghost' ? null : 67.0011 + ($index * 0.001),
                    'accuracy_meters' => 12.5 + $index,
                    'battery_level' => 35 + ($index % 60),
                    'is_charging' => $index % 4 === 0,
                    'network_type' => $index % 2 === 0 ? 'wifi' : '5g',
                    'movement_type' => $index % 3 === 0 ? 'walking' : 'stationary',
                    'location_updated_at' => $lastSeen,
                    'reported_at' => $lastSeen,
                    'created_at' => $user->created_at,
                    'updated_at' => $lastSeen,
                ]);
            }
            $index++;
        }

        $user = $this->users['multi_device'];
        $second = $this->uuid('device-multi-device-tablet');
        $this->upsert('devices', ['id' => $second], [
            'user_id' => $user->id, 'client_device_id' => 'demo-multi-device-tablet', 'name' => 'Lina’s Tablet', 'device_name' => 'Lina’s Tablet',
            'platform' => 'ios', 'app_version' => '2.6.0', 'os_version' => 'iPadOS 19.1', 'public_identity_key' => 'demo-public-key-lina-tablet',
            'push_token' => 'demo-push-token-lina-tablet', 'last_seen_at' => now()->subHours(2), 'revoked_at' => null, 'created_at' => now()->subDays(60), 'updated_at' => now()->subHours(2),
        ]);

        $this->upsert('admin_device_controls', ['device_id' => $this->devices['suspicious_device']], [
            'suspicious' => true, 'require_verification' => true, 'enforcement_revoked' => false, 'reason' => 'New IP + unusual device fingerprint demo',
            'updated_by_admin_id' => $this->admins['security-administrator']->id, 'created_at' => now()->subHours(5), 'updated_at' => now()->subHours(1),
        ]);
    }

    private function seedIdentitySessions(): void
    {
        $i = 0;
        foreach ($this->devices as $userKey => $deviceId) {
            $user = $this->users[$userKey];
            $lastSeen = match ($userKey) {
                'dormant', 'inactive_paid' => now()->subDays(45),
                default => now()->subMinutes(($i * 17) % 1200),
            };
            $this->upsert('identity_sessions', ['id' => $this->uuid('identity-session-'.$userKey)], [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'access_token_id' => null,
                'refresh_family_id' => $this->uuid('refresh-family-'.$userKey),
                'status' => in_array($userKey, ['suspended_indefinite'], true) ? 'revoked' : 'active',
                'device_key_fingerprint' => hash('sha256', 'demo-fingerprint-'.$userKey),
                'last_seen_at' => $lastSeen,
                'access_expires_at' => now()->addMinutes(15),
                'refresh_expires_at' => now()->addDays(60),
                'revoked_at' => $userKey === 'suspended_indefinite' ? now()->subDays(10) : null,
                'revoke_reason' => $userKey === 'suspended_indefinite' ? 'administrative_suspension' : null,
                'created_at' => $user->created_at,
                'updated_at' => $lastSeen,
            ]);
            $i++;
        }
    }

    private function seedOperationalUserStates(): void
    {
        $rows = [
            'suspended_temp' => ['suspended', now()->addDays(4), 'Repeated harassment reports', null, false, 'high', 'Temporary suspension under review'],
            'suspended_indefinite' => ['suspended', null, 'Severe policy violation', null, false, 'critical', 'Indefinite suspension'],
            'reverification' => ['active', null, null, null, true, 'elevated', 'Identity reverification required'],
            'restricted' => ['active', null, null, ['messaging' => true, 'ping' => true], false, 'elevated', 'Messaging and Ping restricted'],
            'high_risk' => ['active', null, null, null, false, 'critical', 'High-risk account under Trust & Safety review'],
            'reported' => ['active', null, null, null, false, 'high', 'Multiple reports in last 30 days'],
            'appeal_pending' => ['suspended', now()->addDays(12), 'Enforcement pending appeal', null, false, 'high', 'Appeal submitted'],
        ];

        foreach ($rows as $key => [$status, $until, $reason, $restrictions, $reverify, $risk, $warning]) {
            $this->upsert('admin_user_controls', ['user_id' => $this->users[$key]->id], [
                'status' => $status, 'suspended_until' => $until, 'suspension_reason' => $reason,
                'feature_restrictions' => $restrictions ? $this->json($restrictions) : null,
                'rate_limit_per_minute' => $key === 'high_risk' ? 30 : null,
                'require_reverification' => $reverify, 'risk_level' => $risk, 'warning' => $warning,
                'trust_safety_escalated_at' => in_array($risk, ['high', 'critical'], true) ? now()->subHours(12) : null,
                'updated_by_admin_id' => $this->admins['moderator']->id,
                'created_at' => now()->subDays(5), 'updated_at' => now()->subHours(2),
            ]);
        }

        $deletion = $this->uuid('deletion-request-demo');
        $this->upsert('account_deletion_requests', ['id' => $deletion], [
            'user_id' => $this->users['deletion_pending']->id, 'status' => 'pending', 'reason' => 'Moving to another service', 'blocking_reason' => null,
            'requested_at' => now()->subDays(9), 'scheduled_for' => now()->addDays(21), 'cancelled_at' => null, 'completed_at' => null,
            'created_at' => now()->subDays(9), 'updated_at' => now()->subDays(9),
        ]);

        $export = $this->uuid('data-export-demo');
        $this->upsert('data_export_requests', ['id' => $export], [
            'user_id' => $this->users['export_pending']->id, 'status' => 'ready',
            'payload' => $this->json(['profile' => ['name' => $this->users['export_pending']->name], 'generated_for_demo' => true]),
            'requested_at' => now()->subDays(2), 'completed_at' => now()->subDays(2)->addMinutes(2), 'expires_at' => now()->addDays(5),
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
    }

    private function seedEngagement(): void
    {
        $pairs = [
            ['family', 'owner_plus', 'plus_family'],
            ['friends', 'free_online', 'lite_active'],
            ['campus', 'student', 'new_today'],
            ['travel', 'traveler', 'multi_device'],
            ['work', 'night_shift', 'reverification'],
        ];

        foreach ($pairs as $i => [$circleKey, $senderKey, $recipientKey]) {
            $senderMembership = $this->uuid("membership-{$circleKey}-{$senderKey}");
            $recipientMembership = $this->uuid("membership-{$circleKey}-{$recipientKey}");
            $this->upsert('pings', ['id' => $this->uuid('ping-'.$i)], [
                'circle_id' => $this->circles[$circleKey], 'sender_membership_id' => $senderMembership, 'recipient_membership_id' => $recipientMembership,
                'status' => $i % 2 === 0 ? 'responded' : 'pending', 'response_type' => $i % 2 === 0 ? 'hey' : null,
                'expires_at' => now()->addHours(2), 'responded_at' => $i % 2 === 0 ? now()->subMinutes(20 + $i) : null, 'dismissed_at' => null,
                'created_at' => now()->subMinutes(30 + $i * 12), 'updated_at' => now()->subMinutes(20 + $i * 8),
            ]);

            $this->upsert('messages', ['id' => $this->uuid('message-'.$i)], [
                'circle_id' => $this->circles[$circleKey], 'sender_user_id' => $this->users[$senderKey]->id, 'sender_device_id' => $this->devices[$senderKey],
                'type' => 'text', 'client_sent_at' => now()->subMinutes(45 + $i * 6), 'expires_at' => now()->addDays(7),
                'created_at' => now()->subMinutes(45 + $i * 6), 'updated_at' => now()->subMinutes(44 + $i * 6),
            ]);

            $this->upsert('activity_events', ['id' => $this->uuid('activity-'.$i)], [
                'circle_id' => $this->circles[$circleKey], 'actor_user_id' => $this->users[$senderKey]->id,
                'event_type' => $i % 2 === 0 ? 'member_joined' : 'ping_sent', 'source_type' => 'demo', 'source_id' => 'demo-'.$i,
                'event_key' => 'demo-activity-'.$i, 'payload' => $this->json(['safe' => true, 'label' => 'Demo activity']),
                'occurred_at' => now()->subHours($i + 1), 'removed_at' => null, 'created_at' => now()->subHours($i + 1), 'updated_at' => now()->subHours($i + 1),
            ]);
        }
    }

    private function seedSos(): void
    {
        $rows = [
            ['active-primary', 'owner_plus', 'family', 'active', 1, now()->subMinutes(22), null, null, false, false, false],
            ['active-escalated', 'student', 'campus', 'active', 2, now()->subMinutes(48), null, null, false, false, false],
            ['resolved', 'traveler', 'travel', 'resolved', 1, now()->subDays(2), now()->subDays(2)->addMinutes(33), 'safe', false, false, false],
            ['false-alarm', 'free_online', 'friends', 'resolved', 0, now()->subDays(4), now()->subDays(4)->addMinutes(4), 'false_alarm', true, false, false],
            ['technical', 'night_shift', 'work', 'resolved', 1, now()->subDays(6), now()->subDays(6)->addMinutes(18), 'technical_issue', false, true, false],
        ];

        foreach ($rows as $i => [$key, $userKey, $circleKey, $status, $stage, $activated, $resolved, $reason, $falseAlarm, $technical, $abuse]) {
            $id = $this->uuid('sos-'.$key);
            $this->upsert('sos_events', ['id' => $id], [
                'user_id' => $this->users[$userKey]->id, 'circle_id' => $this->circles[$circleKey], 'status' => $status, 'escalation_stage' => $stage,
                'activated_at' => $activated, 'resolved_at' => $resolved, 'resolution_reason' => $reason,
                'recording_ref' => $status === 'active' ? 'encrypted://demo/'.$id : null, 'recording_expires_at' => $status === 'active' ? now()->addDays(7) : null,
                'last_latitude' => 24.8607 + ($i * 0.01), 'last_longitude' => 67.0011 + ($i * 0.01), 'last_location_accuracy_m' => 8 + $i,
                'last_location_at' => $activated->copy()->addMinutes(3), 'created_at' => $activated, 'updated_at' => $resolved ?? now()->subMinutes(3),
            ]);
            $this->upsert('admin_sos_incident_controls', ['sos_event_id' => $id], [
                'assigned_admin_id' => $this->admins[$i === 1 ? 'senior-safety-operator' : 'safety-operator']->id,
                'operational_status' => $status === 'active' ? 'open' : 'closed', 'internal_escalation_level' => $stage >= 2 ? 'high' : 'normal',
                'false_alarm' => $falseAlarm, 'technical_failure' => $technical, 'abuse_flag' => $abuse,
                'operational_resolution' => $resolved ? 'Demo incident reviewed and closed.' : null,
                'updated_by_admin_id' => $this->admins['safety-operator']->id, 'created_at' => $activated, 'updated_at' => $resolved ?? now()->subMinutes(3),
            ]);
        }
    }

    private function seedModerationAndRisk(): void
    {
        $reports = [
            ['harassment-new', 'reported', 'high', 'new', 'harassment', 82],
            ['spam-review', 'high_risk', 'normal', 'in_review', 'spam', 58],
            ['threat-escalated', 'suspended_temp', 'urgent', 'escalated', 'threats', 94],
            ['impersonation-actioned', 'appeal_pending', 'high', 'actioned', 'impersonation', 76],
            ['misinformation-closed', 'restricted', 'low', 'closed', 'other', 31],
        ];

        foreach ($reports as $i => [$key, $targetKey, $priority, $status, $reason, $score]) {
            $id = $this->uuid('moderation-report-'.$key);
            $reporter = $this->users[$i % 2 === 0 ? 'support_vip' : 'free_online'];
            $this->upsert('moderation_reports', ['id' => $id], [
                'client_report_id' => $this->uuid('client-report-'.$key), 'reporter_user_id' => $reporter->id,
                'target_type' => 'user', 'target_id' => (string) $this->users[$targetKey]->id, 'target_user_id' => $this->users[$targetKey]->id,
                'source' => 'consumer', 'source_report_id' => null, 'reason' => $reason, 'details' => 'Demo moderation case for UI and workflow testing.',
                'evidence' => $this->json(['reporter_note' => 'Explicit demo evidence only']), 'target_snapshot' => $this->json(['user_id' => $this->users[$targetKey]->id]),
                'status' => $status, 'priority' => $priority, 'risk_score' => $score,
                'assigned_admin_id' => in_array($status, ['in_review', 'escalated', 'actioned'], true) ? $this->admins['moderator']->id : null,
                'triaged_at' => $status !== 'new' ? now()->subHours(10 - $i) : null, 'review_started_at' => in_array($status, ['in_review', 'escalated', 'actioned', 'closed'], true) ? now()->subHours(8 - $i) : null,
                'actioned_at' => in_array($status, ['actioned', 'closed'], true) ? now()->subHours(4) : null, 'escalated_at' => $status === 'escalated' ? now()->subHours(3) : null,
                'closed_at' => $status === 'closed' ? now()->subHour() : null, 'created_at' => now()->subDays($i + 1), 'updated_at' => now()->subHours($i + 1),
            ]);
        }

        $enforcement = $this->uuid('moderation-enforcement-appeal');
        $reportId = $this->uuid('moderation-report-impersonation-actioned');
        $target = $this->users['appeal_pending'];
        $this->upsert('moderation_enforcements', ['id' => $enforcement], [
            'report_id' => $reportId, 'target_type' => 'user', 'target_id' => (string) $target->id, 'action' => 'temporary_suspension',
            'parameters' => $this->json(['days' => 14]), 'reason' => 'Demo moderation enforcement', 'admin_user_id' => $this->admins['moderator']->id,
            'status' => 'applied', 'applied_at' => now()->subDays(3), 'reversed_at' => null, 'reversed_by_admin_id' => null, 'reversal_reason' => null,
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);
        $this->upsert('moderation_appeals', ['id' => $this->uuid('moderation-appeal-pending')], [
            'enforcement_id' => $enforcement, 'user_id' => $target->id, 'explanation' => 'Demo user disputes the enforcement and requests review.',
            'status' => 'submitted', 'assigned_admin_id' => $this->admins['moderator']->id, 'outcome' => null, 'decision_reason' => null,
            'review_metadata' => null, 'reviewer_admin_id' => null, 'requires_second_review' => true, 'second_reviewer_admin_id' => null,
            'submitted_at' => now()->subDays(2), 'reviewed_at' => null, 'second_reviewed_at' => null, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);

        foreach (['reported' => [78, 'high'], 'high_risk' => [96, 'critical'], 'suspended_temp' => [85, 'high'], 'appeal_pending' => [72, 'high']] as $key => [$score, $level]) {
            $this->upsert('admin_risk_profiles', ['user_id' => $this->users[$key]->id], [
                'score' => $score, 'level' => $level, 'triggered_rules' => $this->json(['report_volume', 'recent_enforcement']),
                'analyst_notes' => 'Demo risk profile for admin UI.', 'last_evaluated_at' => now()->subHour(), 'updated_by_admin_id' => $this->admins['moderator']->id,
                'created_at' => now()->subDays(5), 'updated_at' => now()->subHour(),
            ]);
            $this->upsert('admin_risk_signals', ['id' => $this->uuid('risk-signal-'.$key)], [
                'user_id' => $this->users[$key]->id, 'type' => 'report_volume', 'severity' => $level === 'critical' ? 'critical' : 'high', 'source' => 'demo', 'source_id' => null,
                'metadata' => $this->json(['safe' => true]), 'occurred_at' => now()->subDays(2), 'resolved_at' => null, 'resolved_by_admin_id' => null, 'resolution_note' => null,
                'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
            ]);
        }
    }

    private function seedPrivacyAndSupport(): void
    {
        $privacy = [
            ['access', 'privacy_case', 'new', 'pending', 8],
            ['correction', 'export_pending', 'in_progress', 'verified', 15],
            ['consent', 'support_vip', 'completed', 'verified', 2],
            ['account_deletion', 'deletion_pending', 'in_progress', 'verified', 21],
        ];
        foreach ($privacy as $i => [$type, $userKey, $status, $identity, $deadlineDays]) {
            $this->upsert('privacy_requests', ['id' => $this->uuid('privacy-'.$type.'-'.$userKey)], [
                'user_id' => $this->users[$userKey]->id, 'type' => $type, 'source' => 'consumer', 'status' => $status, 'identity_status' => $identity,
                'assigned_admin_id' => $status === 'new' ? null : $this->admins['compliance-officer']->id,
                'details' => 'Demo privacy request for compliance workflow.', 'resolution' => $status === 'completed' ? 'Demo request completed.' : null,
                'deadline_at' => now()->addDays($deadlineDays), 'linked_data_export_id' => $userKey === 'export_pending' ? $this->uuid('data-export-demo') : null,
                'linked_deletion_id' => $userKey === 'deletion_pending' ? $this->uuid('deletion-request-demo') : null,
                'completed_at' => $status === 'completed' ? now()->subDay() : null,
                'created_at' => now()->subDays($i + 2), 'updated_at' => now()->subHours($i + 2),
            ]);
        }

        $tickets = [
            ['login-help', 'support_vip', 'account', 'high', 'open', 'Cannot access trusted device', -2],
            ['billing-question', 'lite_active', 'billing', 'normal', 'pending', 'Question about Lite renewal', 5],
            ['safety-followup', 'safety_responder', 'safety', 'urgent', 'escalated', 'Follow-up after SOS incident', -1],
            ['privacy-help', 'privacy_case', 'privacy', 'normal', 'new', 'Need help with data access request', 8],
            ['resolved-ticket', 'free_online', 'general', 'low', 'resolved', 'How to rename a Circle', 0],
        ];

        foreach ($tickets as $i => [$key, $userKey, $category, $priority, $status, $subject, $slaOffset]) {
            $ticketId = $this->uuid('support-'.$key);
            $this->upsert('support_tickets', ['id' => $ticketId], [
                'user_id' => $this->users[$userKey]->id, 'category' => $category, 'priority' => $priority, 'status' => $status, 'subject' => $subject,
                'assigned_admin_id' => $status === 'new' ? null : $this->admins['support-agent']->id,
                'sla_due_at' => now()->addHours($slaOffset), 'last_message_at' => now()->subMinutes(20 + $i * 10),
                'escalated_at' => $status === 'escalated' ? now()->subHour() : null, 'resolved_at' => $status === 'resolved' ? now()->subHours(3) : null,
                'created_at' => now()->subDays($i + 1), 'updated_at' => now()->subMinutes(20 + $i * 10),
            ]);
            $this->upsert('support_messages', ['id' => $this->uuid('support-message-'.$key)], [
                'support_ticket_id' => $ticketId, 'actor_type' => 'user', 'actor_user_id' => $this->users[$userKey]->id, 'actor_admin_id' => null,
                'body' => 'Demo support message: '.$subject, 'attachment_refs' => null, 'internal' => false, 'created_at' => now()->subHours($i + 2),
            ], timestamps: false);
        }
    }

    private function seedBillingAndAdvertising(): void
    {
        $free = BillingPlan::query()->where('slug', 'free')->firstOrFail();
        $lite = BillingPlan::query()->where('slug', 'lite')->firstOrFail();
        $plus = BillingPlan::query()->where('slug', 'plus')->firstOrFail();

        foreach ([[$lite, 'monthly', 'USD', 499], [$plus, 'monthly', 'USD', 999], [$lite, 'monthly', 'PKR', 1399], [$plus, 'monthly', 'PKR', 2799]] as [$plan, $interval, $currency, $amount]) {
            $this->upsert('billing_plan_prices', ['id' => $this->uuid("price-{$plan->slug}-{$interval}-{$currency}")], [
                'plan_id' => $plan->id, 'billing_interval' => $interval, 'currency' => $currency, 'amount_minor' => $amount,
                'provider' => 'manual', 'provider_price_ref' => null, 'starts_at' => now()->subMonths(3), 'ends_at' => null,
                'created_at' => now()->subMonths(3), 'updated_at' => now()->subMonths(3),
            ]);
        }

        $subscriptions = [
            ['owner_plus', $plus, 999, 'USD', 'active', false],
            ['plus_family', $plus, 2799, 'PKR', 'active', false],
            ['lite_active', $lite, 499, 'USD', 'active', false],
            ['support_vip', $plus, 0, 'USD', 'active', true],
            ['inactive_paid', $plus, 999, 'USD', 'cancel_pending', false],
        ];
        foreach ($subscriptions as $i => [$userKey, $plan, $amount, $currency, $status, $complimentary]) {
            $subId = $this->uuid('subscription-'.$userKey);
            $this->upsert('user_subscriptions', ['id' => $subId], [
                'user_id' => $this->users[$userKey]->id, 'plan_id' => $plan->id, 'status' => $status, 'source' => 'admin', 'provider' => 'manual',
                'provider_subscription_ref' => null, 'price_amount_minor' => $amount, 'price_currency' => $currency, 'billing_interval' => 'monthly',
                'complimentary' => $complimentary, 'promotion_id' => null, 'created_by_admin_id' => $this->admins['finance-manager']->id,
                'started_at' => now()->subMonths(4 - $i), 'current_period_end' => now()->addDays(18 - $i),
                'cancel_at' => $status === 'cancel_pending' ? now()->addDays(8) : null, 'cancelled_at' => null, 'ends_at' => null,
                'created_at' => now()->subMonths(4 - $i), 'updated_at' => now()->subHours($i + 1),
            ]);
            if (! $complimentary) {
                $txId = $this->uuid('payment-'.$userKey);
                $this->upsert('payment_transactions', ['id' => $txId], [
                    'user_id' => $this->users[$userKey]->id, 'subscription_id' => $subId, 'provider' => 'manual',
                    'provider_transaction_ref' => 'demo-tx-'.$userKey, 'type' => 'charge', 'amount_minor' => $amount, 'currency' => $currency,
                    'status' => $userKey === 'inactive_paid' ? 'failed' : 'succeeded', 'failure_code' => $userKey === 'inactive_paid' ? 'payment_declined' : null,
                    'metadata' => $this->json(['demo' => true, 'channel' => 'manual']), 'occurred_at' => now()->subDays($i * 5 + 1),
                    'created_at' => now()->subDays($i * 5 + 1), 'updated_at' => now()->subDays($i * 5 + 1),
                ]);
            }
        }

        $paidKeys = ['owner_plus', 'plus_family', 'lite_active', 'support_vip', 'inactive_paid'];
        foreach ($this->users as $userKey => $user) {
            if (in_array($userKey, $paidKeys, true)) {
                continue;
            }
            $subId = $this->uuid('subscription-'.$userKey);
            $this->upsert('user_subscriptions', ['id' => $subId], [
                'user_id' => $user->id, 'plan_id' => $free->id, 'status' => 'active', 'source' => 'system', 'provider' => 'manual',
                'provider_subscription_ref' => null, 'price_amount_minor' => 0, 'price_currency' => 'USD', 'billing_interval' => 'monthly',
                'complimentary' => false, 'promotion_id' => null, 'created_by_admin_id' => null, 'started_at' => $user->created_at,
                'current_period_end' => null, 'cancel_at' => null, 'cancelled_at' => null, 'ends_at' => null,
                'created_at' => $user->created_at, 'updated_at' => now()->subHours(2),
            ]);
        }

        $ownerTx = $this->uuid('payment-owner_plus');
        $this->upsert('payment_refunds', ['id' => $this->uuid('refund-owner-plus')], [
            'payment_transaction_id' => $ownerTx, 'user_id' => $this->users['owner_plus']->id, 'amount_minor' => 250, 'currency' => 'USD',
            'status' => 'succeeded', 'reason' => 'Demo partial goodwill refund', 'internal_note' => 'Seeded refund for Finance UI.', 'provider_ref' => 'demo-refund-001', 'provider_result' => 'manual_success',
            'requested_by_admin_id' => $this->admins['finance-manager']->id, 'decided_by_admin_id' => $this->admins['finance-manager']->id,
            'requested_at' => now()->subDays(3), 'decided_at' => now()->subDays(3)->addMinutes(20), 'completed_at' => now()->subDays(3)->addMinutes(21),
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);

        $advertiser = $this->uuid('advertiser-orbit-demo');
        $campaign = $this->uuid('ad-campaign-demo');
        $creative = $this->uuid('ad-creative-demo');
        $this->upsert('advertisers', ['id' => $advertiser], [
            'name' => 'Northstar Coffee Demo', 'status' => 'active', 'external_ref' => 'demo-northstar', 'contact_email' => 'ads@demo.orbit.test',
            'created_at' => now()->subMonths(2), 'updated_at' => now()->subDay(),
        ]);
        $this->upsert('ad_campaigns', ['id' => $campaign], [
            'advertiser_id' => $advertiser, 'name' => 'Morning Feed Demo', 'status' => 'active', 'placement' => 'feed_card',
            'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(20), 'targeting' => $this->json(['plans' => ['free'], 'countries' => ['PK'], 'platforms' => ['android', 'ios']]),
            'impression_cap_per_user' => 3, 'budget_minor' => 250000, 'currency' => 'PKR', 'priority' => 100,
            'created_by_admin_id' => $this->admins['advertising-manager']->id, 'created_at' => now()->subDays(8), 'updated_at' => now()->subDay(),
        ]);
        $this->upsert('ad_creatives', ['id' => $creative], [
            'campaign_id' => $campaign, 'type' => 'card', 'status' => 'active', 'title' => 'Start your morning nearby', 'body' => 'Demo sponsored card for Orbit admin testing.',
            'media_ref' => null, 'deep_link' => 'orbit://demo/sponsor', 'cta' => 'View offer', 'metadata' => $this->json(['sponsored' => true]),
            'created_at' => now()->subDays(8), 'updated_at' => now()->subDay(),
        ]);
        foreach (['impression', 'click', 'impression'] as $i => $event) {
            $this->upsert('ad_events', ['id' => $this->uuid('ad-event-'.$i)], [
                'campaign_id' => $campaign, 'creative_id' => $creative, 'user_id' => $this->users['ad_engaged']->id, 'event_type' => $event,
                'client_event_id' => 'demo-ad-event-'.$i, 'context' => $this->json(['country' => 'PK', 'platform' => 'android']), 'occurred_at' => now()->subHours($i + 1),
            ], timestamps: false);
        }
    }

    private function seedCommunicationsAndContent(): void
    {
        $campaigns = [
            ['welcome-demo', 'in_app', 'sent', 'Welcome to Orbit', 'A demo lifecycle campaign.', false],
            ['safety-demo', 'push', 'scheduled', 'Safety check-in tips', 'Review your Circle safety settings.', false],
            ['maintenance-demo', 'system_banner', 'sent', 'Platform update complete', 'Orbit services are operating normally.', false],
        ];
        foreach ($campaigns as $i => [$key, $channel, $status, $title, $body, $emergency]) {
            $id = $this->uuid('comm-'.$key);
            $this->upsert('communication_campaigns', ['id' => $id], [
                'name' => ucfirst(str_replace('-', ' ', $key)), 'channel' => $channel, 'category' => $channel === 'system_banner' ? 'system' : 'product',
                'status' => $status, 'priority' => $channel === 'system_banner' ? 'high' : 'normal',
                'is_emergency' => $emergency, 'template_id' => null, 'locale' => 'en', 'subject' => $channel === 'email' ? $title : null, 'title' => $title, 'body' => $body,
                'deep_link' => 'orbit://demo/'.$key, 'audience' => $this->json(['mode' => 'all']), 'scheduled_at' => $status === 'scheduled' ? now()->addHours(4) : null,
                'sent_at' => $status === 'sent' ? now()->subDays($i + 1) : null, 'cancelled_at' => null,
                'stats' => $this->json(['targeted' => 30, 'delivered' => $status === 'sent' ? 27 : 0, 'failed' => $status === 'sent' ? 3 : 0]),
                'created_by_admin_id' => $this->admins['marketing-manager']->id, 'approved_by_admin_id' => $this->admins['marketing-manager']->id,
                'approved_at' => now()->subDays($i + 2), 'created_at' => now()->subDays($i + 3), 'updated_at' => now()->subHours($i + 1),
            ]);
        }

        $announcement = $this->uuid('announcement-demo');
        $this->upsert('announcements', ['id' => $announcement], [
            'type' => 'product', 'status' => 'published', 'priority' => 'normal', 'dismissible' => true, 'deep_link' => 'orbit://settings/privacy',
            'audience' => $this->json(['mode' => 'all']), 'starts_at' => now()->subDays(2), 'ends_at' => now()->addDays(20), 'published_at' => now()->subDays(2),
            'created_by_admin_id' => $this->admins['marketing-manager']->id, 'published_by_admin_id' => $this->admins['marketing-manager']->id,
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(2),
        ]);
        $this->upsert('announcement_translations', ['id' => $this->uuid('announcement-demo-en')], [
            'announcement_id' => $announcement, 'locale' => 'en', 'status' => 'published', 'title' => 'Privacy controls are easier to find',
            'body' => 'We reorganized privacy settings for quicker access.', 'reviewed_by_admin_id' => $this->admins['marketing-manager']->id,
            'published_at' => now()->subDays(2), 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(2),
        ]);

        $content = $this->uuid('content-demo-safety');
        $this->upsert('content_items', ['id' => $content], [
            'type' => 'safety', 'slug' => 'demo-safety-guide', 'status' => 'published', 'regions' => $this->json(['PK', 'GB', 'US']),
            'scheduled_at' => null, 'published_at' => now()->subDays(7), 'created_by_admin_id' => $this->admins['marketing-manager']->id,
            'published_by_admin_id' => $this->admins['marketing-manager']->id, 'created_at' => now()->subDays(9), 'updated_at' => now()->subDays(7),
        ]);
        $this->upsert('content_translations', ['id' => $this->uuid('content-demo-safety-en')], [
            'content_item_id' => $content, 'locale' => 'en', 'status' => 'published', 'title' => 'Safety check-in guide', 'body' => 'Demo CMS content for the Orbit admin console.',
            'reviewed_by_admin_id' => $this->admins['marketing-manager']->id, 'published_at' => now()->subDays(7), 'created_at' => now()->subDays(9), 'updated_at' => now()->subDays(7),
        ]);

        $legal = $this->uuid('legal-demo-terms');
        $this->upsert('legal_documents', ['id' => $legal], [
            'document_type' => 'terms', 'version' => 'demo-2026.09', 'status' => 'published', 'regions' => $this->json(['PK', 'US', 'GB']), 'requires_reacceptance' => false,
            'effective_at' => now()->subDays(20), 'published_at' => now()->subDays(25), 'created_by_admin_id' => $this->admins['compliance-officer']->id,
            'published_by_admin_id' => $this->admins['compliance-officer']->id, 'created_at' => now()->subDays(30), 'updated_at' => now()->subDays(25),
        ]);
        $this->upsert('legal_document_translations', ['id' => $this->uuid('legal-demo-terms-en')], [
            'legal_document_id' => $legal, 'locale' => 'en', 'status' => 'published', 'title' => 'Orbit Demo Terms', 'body' => 'Demo legal document used only in local development.',
            'reviewed_by_admin_id' => $this->admins['compliance-officer']->id, 'published_at' => now()->subDays(25), 'created_at' => now()->subDays(30), 'updated_at' => now()->subDays(25),
        ]);

        foreach (['PK' => [false, 'PKR'], 'US' => [true, 'USD'], 'GB' => [true, 'GBP']] as $country => [$sms, $currency]) {
            $this->upsert('regional_configurations', ['country_code' => $country], [
                'id' => $this->uuid('region-'.$country), 'status' => 'active', 'feature_availability' => $this->json(['sos' => true, 'moments' => true, 'ads' => true]),
                'subscription_availability' => $this->json(['free', 'lite', 'plus']), 'pricing' => $this->json(['currency' => $currency]),
                'legal_disclosures' => $this->json(['demo' => true]), 'sms_available' => $sms, 'emergency_information' => $this->json(['local_emergency_number' => $country === 'PK' ? '15' : '911']),
                'consent_requirements' => $this->json(['analytics' => true]), 'retention_rules' => $this->json(['demo' => 'default']),
                'updated_by_admin_id' => $this->admins['compliance-officer']->id, 'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(2),
            ]);
        }
    }

    private function seedOperationsAndAnalytics(): void
    {
        $flags = [
            ['demo.new_dashboard', 'New dashboard experiment', 'active', true, 60],
            ['demo.map_refresh', 'Map refresh experiment', 'active', false, 25],
            ['demo.future_feature', 'Future feature preview', 'disabled', false, 0],
        ];
        foreach ($flags as [$key, $name, $status, $default, $rollout]) {
            $this->upsert('feature_flags', ['key' => $key], [
                'id' => $this->uuid('feature-'.$key), 'name' => $name, 'description' => 'Demo feature flag for UI testing.', 'environment' => 'production', 'status' => $status,
                'default_enabled' => $default, 'rollout_percentage' => $rollout, 'targeting' => $this->json(['countries' => ['PK', 'US']]), 'starts_at' => now()->subDays(4),
                'ends_at' => null, 'removal_at' => null, 'archived_at' => null, 'owner_admin_id' => $this->admins['devops-operator']->id,
                'updated_by_admin_id' => $this->admins['devops-operator']->id, 'created_at' => now()->subDays(10), 'updated_at' => now()->subDay(),
            ]);
        }

        foreach ([
            ['ui.poll_interval_seconds', false, 30],
            ['maps.default_zoom', false, 13],
            ['safety.banner_enabled', true, true],
        ] as [$key, $critical, $value]) {
            $this->upsert('remote_config_entries', ['key' => $key, 'environment' => 'production'], [
                'id' => $this->uuid('remote-'.$key), 'status' => 'active', 'critical' => $critical, 'value' => $this->json(['value' => $value]),
                'description' => 'Demo non-secret runtime configuration.', 'updated_by_admin_id' => $this->admins['devops-operator']->id,
                'created_at' => now()->subDays(6), 'updated_at' => now()->subHours(4),
            ]);
        }

        $incidents = [
            ['push-delay', 'notifications', 'medium', 'investigating', 'Push delivery latency elevated', null],
            ['websocket-lag', 'websocket', 'high', 'monitoring', 'Realtime fan-out lag in one region', null],
            ['resolved-storage', 'object_storage', 'low', 'resolved', 'Transient storage error rate', 'Provider recovered; backlog drained.'],
        ];
        foreach ($incidents as $i => [$key, $service, $severity, $status, $title, $resolution]) {
            $this->upsert('system_incidents', ['id' => $this->uuid('incident-'.$key)], [
                'title' => $title, 'service' => $service, 'severity' => $severity, 'status' => $status, 'impact' => 'Demo operational incident for admin UI.',
                'assigned_admin_id' => $this->admins['devops-operator']->id, 'started_at' => now()->subHours(6 + $i * 5),
                'resolved_at' => $status === 'resolved' ? now()->subHours(2) : null, 'resolution' => $resolution,
                'external_reference' => 'DEMO-INC-'.($i + 1), 'created_by_admin_id' => $this->admins['devops-operator']->id,
                'created_at' => now()->subHours(6 + $i * 5), 'updated_at' => now()->subHour(),
            ]);
        }

        foreach ([
            ['queue_backlog', 'high', 'open', 'Queue backlog elevated', 'Some non-critical background jobs are delayed.'],
            ['push_failures', 'medium', 'open', 'Push failure rate elevated', 'Provider-neutral push boundary reports elevated failures.'],
            ['websocket_recovered', 'low', 'acknowledged', 'Realtime latency recovered', 'Realtime latency returned to normal levels.'],
        ] as $i => [$kind, $severity, $status, $title, $message]) {
            $this->upsert('admin_operational_alerts', ['id' => $this->uuid('alert-'.$kind)], [
                'kind' => $kind, 'severity' => $severity, 'status' => $status, 'resource_type' => 'demo', 'resource_id' => 'demo-'.$i,
                'title' => $title, 'message' => $message, 'metadata' => $this->json(['demo' => true]),
                'acknowledged_at' => $status === 'acknowledged' ? now()->subMinutes(30) : null,
                'acknowledged_by_admin_id' => $status === 'acknowledged' ? $this->admins['devops-operator']->id : null,
                'created_at' => now()->subHours($i + 1), 'updated_at' => now()->subMinutes(20),
            ]);
        }

        foreach (range(0, 23) as $hour) {
            $this->upsert('api_request_metrics', ['request_id' => 'demo-metric-'.$hour], [
                'method' => $hour % 5 === 0 ? 'POST' : 'GET', 'route' => $hour % 3 === 0 ? 'api/v1/circles' : 'api/v1/dashboard/summary',
                'status_code' => $hour % 11 === 0 ? 500 : 200, 'latency_ms' => 80 + ($hour * 7), 'is_admin' => $hour % 4 === 0,
                'occurred_at' => now()->subHours(23 - $hour),
            ], timestamps: false);
        }

        $this->upsert('websocket_metric_snapshots', ['id' => $this->uuid('ws-snapshot-demo')], [
            'environment' => 'production', 'connections' => 184, 'subscriptions' => 623, 'connect_rate' => 42, 'disconnect_rate' => 37, 'reconnect_rate' => 5,
            'fanout_lag_ms' => 76, 'regions' => $this->json(['pk' => 84, 'us' => 61, 'eu' => 39]), 'captured_at' => now()->subMinutes(5),
            'recorded_by_admin_id' => $this->admins['devops-operator']->id, 'created_at' => now()->subMinutes(5), 'updated_at' => now()->subMinutes(5),
        ]);

        $this->upsert('webhook_deliveries', ['id' => $this->uuid('webhook-demo-failed')], [
            'provider' => 'provider_neutral', 'event_type' => 'payment.updated', 'provider_delivery_ref' => 'demo-wh-001', 'endpoint_host' => 'hooks.demo.invalid',
            'status' => 'failed', 'attempt_count' => 3, 'payload_hash' => hash('sha256', 'demo-payload'), 'last_error' => 'Demo timeout for UI testing.',
            'last_delivery_at' => now()->subMinutes(18), 'retry_requested_at' => null, 'created_at' => now()->subHour(), 'updated_at' => now()->subMinutes(18),
        ]);

        foreach ([
            ['name' => 'Weekly Growth Pulse', 'metrics' => ['users.registrations', 'users.wau', 'circles.created'], 'shared' => true, 'schedule' => 'weekly'],
            ['name' => 'Safety Operations', 'metrics' => ['sos.activations', 'sos.resolved', 'notifications.failures'], 'shared' => false, 'schedule' => null],
            ['name' => 'Revenue Overview', 'metrics' => ['subscriptions.active', 'payments.gross_minor', 'payments.refunds_minor'], 'shared' => true, 'schedule' => 'monthly'],
        ] as $i => $report) {
            $this->upsert('admin_saved_reports', ['id' => $this->uuid('saved-report-'.$i)], [
                'admin_user_id' => $i === 2 ? $this->admins['finance-manager']->id : $this->admins['analyst']->id,
                'name' => $report['name'], 'metrics' => $this->json($report['metrics']), 'filters' => $this->json(['range' => '30d']),
                'group_by' => null, 'comparison' => $i === 0 ? 'previous_period' : null, 'team_shared' => $report['shared'],
                'schedule' => $report['schedule'], 'next_run_at' => $report['schedule'] ? now()->addDays(7) : null, 'last_run_at' => $report['schedule'] ? now()->subDays(7) : null,
                'created_at' => now()->subDays(20 - $i * 3), 'updated_at' => now()->subDays($i + 1),
            ]);
        }

        foreach ([
            ['users-high-risk', 'users', 'personal', ['risk_level' => 'high']],
            ['sos-open', 'sos', 'team', ['status' => 'active']],
            ['support-sla', 'support', 'personal', ['sla_breached' => true]],
        ] as $i => [$key, $module, $scope, $filters]) {
            $this->upsert('admin_saved_views', ['id' => $this->uuid('saved-view-'.$key)], [
                'admin_user_id' => $scope === 'team' ? $this->admins['platform-administrator']->id : $this->admins['read-only']->id,
                'name' => ucwords(str_replace('-', ' ', $key)), 'module' => $module, 'scope' => $scope, 'filters' => $this->json($filters),
                'columns' => $this->json(['name', 'status', 'updated_at']), 'sort' => $this->json(['field' => 'updated_at', 'direction' => 'desc']),
                'created_at' => now()->subDays(6 - $i), 'updated_at' => now()->subHours($i + 2),
            ]);
        }

        foreach ([990001, 990002] as $i => $jobId) {
            $this->upsert('jobs', ['id' => $jobId], [
                'queue' => $i === 0 ? 'notifications' : 'analytics', 'payload' => $this->json(['displayName' => 'OrbitDemoJob', 'demo' => true]),
                'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(12 - $i * 4)->timestamp, 'created_at' => now()->subMinutes(20 - $i * 5)->timestamp,
            ], timestamps: false);
        }
        $failedUuid = $this->uuid('failed-job-demo');
        $this->upsert('failed_jobs', ['id' => 990001], [
            'uuid' => $failedUuid, 'connection' => 'database', 'queue' => 'notifications', 'payload' => $this->json(['displayName' => 'OrbitDemoFailedJob', 'demo' => true]),
            'exception' => 'RuntimeException: Demo provider timeout for operations UI.', 'failed_at' => now()->subMinutes(27),
        ], timestamps: false);
        $this->upsert('admin_queue_actions', ['id' => $this->uuid('queue-action-demo')], [
            'failed_job_uuid' => $failedUuid, 'action' => 'retry', 'status' => 'requested', 'reason' => 'Demo retry request',
            'admin_user_id' => $this->admins['devops-operator']->id, 'result_message' => null, 'processed_at' => null,
            'created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10),
        ]);

        DB::table('integration_statuses')->where('environment', 'production')->update(['health' => 'healthy', 'last_success_at' => now()->subMinutes(7), 'last_failure_at' => null, 'last_error' => null, 'updated_at' => now()]);
        DB::table('integration_statuses')->where('service', 'sms')->update(['health' => 'unconfigured', 'enabled' => false, 'last_success_at' => null, 'updated_at' => now()]);
    }

    private function seedAuditAndNotifications(): void
    {
        foreach (array_slice(array_keys($this->admins), 0, 8) as $i => $slug) {
            $admin = $this->admins[$slug];
            $sessionId = $this->uuid('admin-session-'.$slug);
            $this->upsert('admin_sessions', ['id' => $sessionId], [
                'admin_user_id' => $admin->id, 'access_token_id' => null, 'ip_hash' => hash('sha256', '127.0.0.'.($i + 1)),
                'user_agent_hash' => hash('sha256', 'Orbit Demo Browser'), 'last_seen_at' => now()->subMinutes($i * 9 + 2),
                'idle_expires_at' => now()->addMinutes(30), 'expires_at' => now()->addHours(8), 'reauthenticated_at' => $i % 2 === 0 ? now()->subMinutes(3) : null,
                'mfa_verified_at' => now()->subHours($i + 1), 'revoked_at' => null, 'revoke_reason' => null,
                'created_at' => now()->subHours($i + 1), 'updated_at' => now()->subMinutes($i * 9 + 2),
            ]);
            $this->upsert('admin_login_events', ['id' => $this->uuid('admin-login-'.$slug)], [
                'admin_user_id' => $admin->id, 'email_hash' => hash('sha256', $admin->email), 'event_type' => 'login_success', 'success' => true,
                'suspicious' => false, 'ip_hash' => hash('sha256', '127.0.0.'.($i + 1)), 'user_agent_hash' => hash('sha256', 'Orbit Demo Browser'),
                'failure_code' => null, 'metadata' => $this->json(['demo' => true]), 'occurred_at' => now()->subHours($i + 1),
                'created_at' => now()->subHours($i + 1), 'updated_at' => now()->subHours($i + 1),
            ]);
        }

        foreach ([
            ['dashboard.viewed', 'dashboard', 'home', 'Viewed operational dashboard'],
            ['user.control.updated', 'user', (string) $this->users['high_risk']->id, 'Updated demo risk control'],
            ['support.ticket.assigned', 'support_ticket', $this->uuid('support-login-help'), 'Assigned demo support ticket'],
            ['billing.refund.approved', 'payment_refund', $this->uuid('refund-owner-plus'), 'Approved demo partial refund'],
            ['feature_flag.updated', 'feature_flag', $this->uuid('feature-demo.new_dashboard'), 'Updated demo feature rollout'],
        ] as $i => [$action, $targetType, $targetId, $reason]) {
            $admin = $this->admins[array_keys($this->admins)[$i]];
            $this->upsert('admin_audit_logs', ['id' => $this->uuid('admin-audit-'.$i)], [
                'admin_user_id' => $admin->id, 'admin_session_id' => $this->uuid('admin-session-'.array_keys($this->admins)[$i]), 'action' => $action,
                'target_type' => $targetType, 'target_id' => $targetId, 'result' => 'success', 'reason' => $reason,
                'request_id' => 'demo-request-'.$i, 'ip_hash' => hash('sha256', '127.0.0.'.($i + 1)), 'user_agent_hash' => hash('sha256', 'Orbit Demo Browser'),
                'before_state' => null, 'after_state' => $this->json(['demo' => true]), 'metadata' => $this->json(['seeded' => true]), 'occurred_at' => now()->subHours($i + 1),
            ], timestamps: false);
        }

        foreach ([
            ['high_risk', 'Trust & Safety review notes are available to authorized staff.', 'watchlist'],
            ['support_vip', 'Priority customer used for support workflow demonstrations.', 'vip-demo'],
            ['suspicious_device', 'Device verification requested after unusual login metadata.', 'security-review'],
        ] as $i => [$userKey, $note, $tag]) {
            $this->upsert('admin_record_notes', ['id' => $this->uuid('record-note-'.$userKey)], [
                'admin_user_id' => $this->admins['platform-administrator']->id, 'target_type' => 'user', 'target_id' => (string) $this->users[$userKey]->id,
                'note' => $note, 'created_at' => now()->subDays($i + 1),
            ], timestamps: false);
            $this->upsert('admin_record_tags', ['id' => $this->uuid('record-tag-'.$userKey)], [
                'admin_user_id' => $this->admins['platform-administrator']->id, 'target_type' => 'user', 'target_id' => (string) $this->users[$userKey]->id,
                'tag' => $tag, 'created_at' => now()->subDays($i + 1),
            ], timestamps: false);
        }

        foreach ([
            ['support_vip', 'email', 'support.reply', 'outbound', 'Support follow-up', 'Demo support response sent'],
            ['deletion_pending', 'in_app', 'privacy.deletion', 'outbound', 'Deletion request', 'Account deletion scheduled'],
            ['appeal_pending', 'in_app', 'moderation.appeal', 'inbound', 'Appeal submitted', 'Moderation appeal received'],
        ] as $i => [$userKey, $channel, $kind, $direction, $subject, $summary]) {
            $this->upsert('user_contact_events', ['id' => $this->uuid('contact-event-'.$i)], [
                'user_id' => $this->users[$userKey]->id, 'channel' => $channel, 'kind' => $kind, 'direction' => $direction,
                'subject' => $subject, 'summary' => $summary, 'source_type' => 'demo', 'source_id' => 'demo-'.$i,
                'actor_admin_id' => $this->admins['support-agent']->id, 'metadata' => $this->json(['demo' => true]), 'occurred_at' => now()->subHours($i + 2),
            ], timestamps: false);
        }

        $notifications = [
            ['free_online', 'ping', 'normal', 'Hamza received a Ping'],
            ['owner_plus', 'sos', 'critical', 'SOS update requires attention'],
            ['student', 'moment', 'normal', 'New Moment in Campus Circle'],
            ['support_vip', 'support', 'high', 'Support replied to your ticket'],
        ];
        foreach ($notifications as $i => [$userKey, $kind, $priority, $summary]) {
            $id = $this->uuid('notification-'.$i);
            $this->upsert('orbit_notifications', ['id' => $id], [
                'user_id' => $this->users[$userKey]->id, 'circle_id' => $i < 3 ? $this->circles[array_keys($this->circles)[$i]] : null,
                'kind' => $kind, 'priority' => $priority, 'idempotency_key' => 'demo-notification-'.$i, 'summary' => $summary,
                'payload' => $this->json(['demo' => true]), 'deep_link' => 'orbit://demo/notification/'.$i, 'in_app_visible' => true,
                'read_at' => $i === 0 ? now()->subMinutes(20) : null, 'created_at' => now()->subHours($i + 1), 'updated_at' => now()->subHours($i + 1),
            ]);
            if (isset($this->devices[$userKey])) {
                $this->upsert('notification_deliveries', ['id' => $this->uuid('notification-delivery-'.$i)], [
                    'notification_id' => $id, 'target_user_id' => $this->users[$userKey]->id, 'device_id' => $this->devices[$userKey], 'channel' => 'push',
                    'provider' => 'provider_neutral', 'priority' => $priority, 'collapse_key' => 'demo-'.$kind, 'silent' => false,
                    'payload' => $this->json(['notification_id' => $id, 'kind' => $kind]), 'status' => $i === 3 ? 'failed' : 'pending_provider',
                    'available_at' => now()->subHours($i + 1), 'dispatched_at' => $i === 3 ? now()->subMinutes(40) : null, 'attempts' => $i === 3 ? 2 : 0,
                    'created_at' => now()->subHours($i + 1), 'updated_at' => now()->subMinutes(30),
                ]);
            }
        }
    }

    private function upsert(string $table, array $keys, array $values, bool $timestamps = true): void
    {
        if ($timestamps) {
            if (Schema::hasColumn($table, 'created_at') && ! array_key_exists('created_at', $values)) {
                $values['created_at'] = now();
            }
            if (Schema::hasColumn($table, 'updated_at') && ! array_key_exists('updated_at', $values)) {
                $values['updated_at'] = now();
            }
        }
        DB::table($table)->updateOrInsert($keys, $values);
    }

    private function uuid(string $key): string
    {
        $hex = md5('orbit-demo:'.$key);

        return sprintf('%s-%s-4%s-a%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 13, 3), substr($hex, 17, 3), substr($hex, 20, 12));
    }

    private function json(array $value): string
    {
        return (string) json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
