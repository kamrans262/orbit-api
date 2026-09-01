<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Support\Facades\DB;

final class AdminRbacService
{
    private const array PERMISSIONS = [
        ['name' => 'Admin Access', 'slug' => 'admin.access', 'description' => 'Authenticate into the Orbit administrative API.', 'is_sensitive' => false],
        ['name' => 'View Administrators', 'slug' => 'admins.view', 'description' => 'View administrator identities and safe account state.', 'is_sensitive' => false],
        ['name' => 'Manage Administrators', 'slug' => 'admins.manage', 'description' => 'Invite, activate, deactivate, and assign administrator roles.', 'is_sensitive' => true],
        ['name' => 'View Roles', 'slug' => 'roles.view', 'description' => 'View administrative roles and permissions.', 'is_sensitive' => false],
        ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'description' => 'Create custom roles and change role permissions.', 'is_sensitive' => true],
        ['name' => 'View Admin Sessions', 'slug' => 'sessions.view', 'description' => 'View administrator sessions.', 'is_sensitive' => false],
        ['name' => 'Revoke Admin Sessions', 'slug' => 'sessions.revoke', 'description' => 'Force logout of administrator sessions.', 'is_sensitive' => true],
        ['name' => 'View Admin Audit', 'slug' => 'audit.view', 'description' => 'Read immutable administrator audit history.', 'is_sensitive' => false],
        ['name' => 'View Admin Security', 'slug' => 'security.view', 'description' => 'View administrator login and security events.', 'is_sensitive' => false],
        ['name' => 'Reveal Sensitive Fields', 'slug' => 'sensitive_fields.reveal', 'description' => 'Reveal separately controlled sensitive fields when a domain explicitly supports it.', 'is_sensitive' => true],

        ['name' => 'View Users', 'slug' => 'users.view', 'description' => 'View user directory and safe operational account metadata.', 'is_sensitive' => false],
        ['name' => 'Manage User Controls', 'slug' => 'users.controls.manage', 'description' => 'Suspend, reactivate, restrict, rate-limit, classify, and require re-verification for consumer accounts.', 'is_sensitive' => true],
        ['name' => 'View User Sessions', 'slug' => 'users.sessions.view', 'description' => 'View consumer hardened session metadata.', 'is_sensitive' => false],
        ['name' => 'Revoke User Sessions', 'slug' => 'users.sessions.revoke', 'description' => 'Force logout or revoke consumer hardened sessions.', 'is_sensitive' => true],
        ['name' => 'View User Devices', 'slug' => 'users.devices.view', 'description' => 'View safe consumer device metadata without push tokens or private keys.', 'is_sensitive' => false],
        ['name' => 'Manage User Devices', 'slug' => 'users.devices.manage', 'description' => 'Revoke devices, force token rotation, mark suspicious, and require verification.', 'is_sensitive' => true],
        ['name' => 'Manage User Notes', 'slug' => 'users.notes.manage', 'description' => 'Create and manage internal operational user notes and tags.', 'is_sensitive' => false],
        ['name' => 'View Circles', 'slug' => 'circles.view', 'description' => 'View Circle directory, members, controls, and safe operational metadata.', 'is_sensitive' => false],
        ['name' => 'Manage Circle Controls', 'slug' => 'circles.controls.manage', 'description' => 'Freeze, archive, restore, remove, or restrict Circles.', 'is_sensitive' => true],
        ['name' => 'Enforce Circle Membership', 'slug' => 'circles.members.enforce', 'description' => 'Remove non-owner Circle members for enforcement.', 'is_sensitive' => true],
        ['name' => 'Manage Circle Notes', 'slug' => 'circles.notes.manage', 'description' => 'Create and manage internal operational Circle notes and tags.', 'is_sensitive' => false],

        ['name' => 'View SOS Operations', 'slug' => 'sos.view', 'description' => 'View SOS command-center incidents and safe operational metadata.', 'is_sensitive' => false],
        ['name' => 'Manage SOS Operations', 'slug' => 'sos.manage', 'description' => 'Assign, classify, annotate, and operationally manage SOS incidents.', 'is_sensitive' => true],
        ['name' => 'Export SOS Incidents', 'slug' => 'sos.export', 'description' => 'Create audited privacy-preserving SOS incident exports.', 'is_sensitive' => true],
        ['name' => 'Access Precise SOS Location', 'slug' => 'sos.location.access', 'description' => 'View precise SOS location after reauthentication and reason capture.', 'is_sensitive' => true],
        ['name' => 'Access SOS Recording Reference', 'slug' => 'sos.recordings.access', 'description' => 'View the opaque encrypted SOS recording reference after reauthentication and reason capture.', 'is_sensitive' => true],
        ['name' => 'View SOS Sensitive Access History', 'slug' => 'sos.sensitive.audit', 'description' => 'Review immutable sensitive SOS access records.', 'is_sensitive' => true],
    ];

    private const array ROLES = [
        ['name' => 'Super Administrator', 'slug' => 'super-administrator', 'description' => 'Complete administrative foundation control; separately protected sensitive-domain permissions are not implied.'],
        ['name' => 'Platform Administrator', 'slug' => 'platform-administrator', 'description' => 'General platform administration.'],
        ['name' => 'Safety Operator', 'slug' => 'safety-operator', 'description' => 'Safety and SOS operations.'],
        ['name' => 'Senior Safety Operator', 'slug' => 'senior-safety-operator', 'description' => 'Sensitive safety review and escalation.'],
        ['name' => 'Moderator', 'slug' => 'moderator', 'description' => 'Reports, abuse and enforcement workflows.'],
        ['name' => 'Support Agent', 'slug' => 'support-agent', 'description' => 'Customer account assistance and support cases.'],
        ['name' => 'Finance Manager', 'slug' => 'finance-manager', 'description' => 'Subscriptions, payments, refunds and revenue operations.'],
        ['name' => 'Marketing Manager', 'slug' => 'marketing-manager', 'description' => 'Announcements and targeted communications.'],
        ['name' => 'Advertising Manager', 'slug' => 'advertising-manager', 'description' => 'Sponsored content and ad inventory operations.'],
        ['name' => 'Analyst', 'slug' => 'analyst', 'description' => 'Read-only business and product analytics.'],
        ['name' => 'Security Administrator', 'slug' => 'security-administrator', 'description' => 'Security events, sessions and administrator access controls.'],
        ['name' => 'DevOps Operator', 'slug' => 'devops-operator', 'description' => 'Service health, queues and operational tooling.'],
        ['name' => 'Compliance Officer', 'slug' => 'compliance-officer', 'description' => 'Privacy, deletion, export and legal workflows.'],
        ['name' => 'Read Only', 'slug' => 'read-only', 'description' => 'Non-destructive visibility to authorized modules.'],
    ];

    public function syncDefaults(): void
    {
        DB::transaction(function (): void {
            foreach (self::PERMISSIONS as $definition) {
                AdminPermission::query()->updateOrCreate(['slug' => $definition['slug']], $definition);
            }

            foreach (self::ROLES as $definition) {
                $role = AdminRole::query()->updateOrCreate(
                    ['slug' => $definition['slug']],
                    [...$definition, 'is_system' => true],
                );
                $role->permissions()->syncWithoutDetaching($this->defaultPermissionIds($role->slug));
            }
        });
    }

    /** @return list<int> */
    private function defaultPermissionIds(string $roleSlug): array
    {
        $slugs = match ($roleSlug) {
            'super-administrator' => [
                'admin.access', 'admins.view', 'admins.manage', 'roles.view', 'roles.manage',
                'sessions.view', 'sessions.revoke', 'audit.view', 'security.view',
                'users.view', 'users.controls.manage', 'users.sessions.view', 'users.sessions.revoke',
                'users.devices.view', 'users.devices.manage', 'users.notes.manage',
                'circles.view', 'circles.controls.manage', 'circles.members.enforce', 'circles.notes.manage',
                'sos.view', 'sos.manage', 'sos.export',
            ],
            'platform-administrator' => [
                'admin.access', 'admins.view', 'roles.view', 'sessions.view', 'audit.view',
                'users.view', 'users.controls.manage', 'users.sessions.view', 'users.sessions.revoke',
                'users.devices.view', 'users.devices.manage', 'users.notes.manage',
                'circles.view', 'circles.controls.manage', 'circles.members.enforce', 'circles.notes.manage',
                'sos.view',
            ],
            'security-administrator' => [
                'admin.access', 'admins.view', 'roles.view', 'sessions.view', 'sessions.revoke', 'audit.view', 'security.view',
                'users.view', 'users.sessions.view', 'users.sessions.revoke', 'users.devices.view', 'users.devices.manage',
                'sos.view', 'sos.sensitive.audit',
            ],
            'support-agent' => [
                'admin.access', 'users.view', 'users.sessions.view', 'users.devices.view', 'users.notes.manage', 'circles.view',
            ],
            'moderator' => ['admin.access', 'users.view', 'circles.view', 'users.notes.manage', 'circles.notes.manage', 'sos.view'],
            'safety-operator' => [
                'admin.access', 'users.view', 'users.devices.view', 'circles.view',
                'sos.view', 'sos.manage',
            ],
            'senior-safety-operator' => [
                'admin.access', 'users.view', 'users.devices.view', 'circles.view', 'audit.view',
                'sos.view', 'sos.manage', 'sos.export', 'sos.location.access',
                'sos.recordings.access', 'sos.sensitive.audit',
            ],
            'compliance-officer' => ['admin.access', 'users.view', 'users.sessions.view', 'users.devices.view', 'circles.view', 'audit.view', 'sos.view', 'sos.sensitive.audit'],
            'read-only' => [
                'admin.access', 'admins.view', 'roles.view', 'audit.view', 'users.view', 'users.sessions.view', 'users.devices.view', 'circles.view', 'sos.view',
            ],
            default => ['admin.access'],
        };

        return AdminPermission::query()->whereIn('slug', $slugs)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
