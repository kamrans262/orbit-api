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

        ['name' => 'View Reports', 'slug' => 'reports.view', 'description' => 'View moderation reports and privacy-safe case evidence.', 'is_sensitive' => false],
        ['name' => 'Review Reports', 'slug' => 'reports.review', 'description' => 'Triage and move moderation cases through review workflows.', 'is_sensitive' => false],
        ['name' => 'Assign Reports', 'slug' => 'reports.assign', 'description' => 'Assign or reassign moderation cases.', 'is_sensitive' => false],
        ['name' => 'Manage Report Notes', 'slug' => 'reports.notes.manage', 'description' => 'Create internal moderation case notes.', 'is_sensitive' => false],
        ['name' => 'Apply Report Enforcement', 'slug' => 'reports.enforce', 'description' => 'Apply audited user or Circle enforcement from a moderation case.', 'is_sensitive' => true],
        ['name' => 'View Appeals', 'slug' => 'appeals.view', 'description' => 'View consumer enforcement appeals.', 'is_sensitive' => false],
        ['name' => 'Assign Appeals', 'slug' => 'appeals.assign', 'description' => 'Assign appeal reviews to eligible administrators.', 'is_sensitive' => false],
        ['name' => 'Review Appeals', 'slug' => 'appeals.review', 'description' => 'Decide appeals and restore enforcement where authorized.', 'is_sensitive' => true],
        ['name' => 'Second Review Appeals', 'slug' => 'appeals.second_review', 'description' => 'Perform required independent second review of sensitive appeal decisions.', 'is_sensitive' => true],
        ['name' => 'View Risk Center', 'slug' => 'risk.view', 'description' => 'View user abuse and risk profiles and signal timelines.', 'is_sensitive' => false],
        ['name' => 'Manage Risk Signals', 'slug' => 'risk.manage', 'description' => 'Create and resolve audited abuse and risk signals.', 'is_sensitive' => true],
        ['name' => 'View Privacy Requests', 'slug' => 'privacy.view', 'description' => 'View privacy requests, deletion state, and safe export metadata.', 'is_sensitive' => false],
        ['name' => 'Manage Privacy Requests', 'slug' => 'privacy.manage', 'description' => 'Update privacy request workflow, deadlines, and resolution state.', 'is_sensitive' => false],
        ['name' => 'Assign Privacy Requests', 'slug' => 'privacy.assign', 'description' => 'Assign privacy and compliance requests to eligible administrators.', 'is_sensitive' => false],
        ['name' => 'Verify Privacy Identity', 'slug' => 'privacy.identity.verify', 'description' => 'Record a verified identity check for a privacy request after reauthentication.', 'is_sensitive' => true],
        ['name' => 'Manage Data Exports', 'slug' => 'privacy.exports.manage', 'description' => 'Review and regenerate expired or failed user data exports.', 'is_sensitive' => true],
        ['name' => 'Deliver Data Exports', 'slug' => 'privacy.exports.deliver', 'description' => 'Generate and revoke time-limited audited data export delivery links.', 'is_sensitive' => true],
        ['name' => 'Manage Account Deletions', 'slug' => 'privacy.deletions.manage', 'description' => 'Supervise due account deletion finalization or verified cancellation.', 'is_sensitive' => true],
        ['name' => 'View Support', 'slug' => 'support.view', 'description' => 'View support tickets and customer-visible conversation history.', 'is_sensitive' => false],
        ['name' => 'Manage Support', 'slug' => 'support.manage', 'description' => 'Change support priority, workflow, SLA, escalation, and resolution.', 'is_sensitive' => false],
        ['name' => 'Assign Support', 'slug' => 'support.assign', 'description' => 'Assign and reassign support tickets.', 'is_sensitive' => false],
        ['name' => 'Reply to Support', 'slug' => 'support.reply', 'description' => 'Send customer-visible support replies.', 'is_sensitive' => false],
        ['name' => 'Manage Support Notes', 'slug' => 'support.notes.manage', 'description' => 'Create internal support-only notes and links.', 'is_sensitive' => false],
        ['name' => 'View Contact History', 'slug' => 'contact_history.view', 'description' => 'View immutable user contact and operational communication history.', 'is_sensitive' => false],
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
                'reports.view', 'reports.review', 'reports.assign', 'reports.notes.manage', 'reports.enforce',
                'appeals.view', 'appeals.assign', 'appeals.review', 'appeals.second_review', 'risk.view', 'risk.manage',
                'privacy.view', 'privacy.manage', 'privacy.assign',
                'support.view', 'support.manage', 'support.assign', 'support.reply', 'support.notes.manage', 'contact_history.view',
            ],
            'platform-administrator' => [
                'admin.access', 'admins.view', 'roles.view', 'sessions.view', 'audit.view',
                'users.view', 'users.controls.manage', 'users.sessions.view', 'users.sessions.revoke',
                'users.devices.view', 'users.devices.manage', 'users.notes.manage',
                'circles.view', 'circles.controls.manage', 'circles.members.enforce', 'circles.notes.manage',
                'sos.view',
                'reports.view', 'reports.review', 'reports.assign', 'reports.notes.manage',
                'appeals.view', 'appeals.assign', 'risk.view',
                'privacy.view', 'privacy.manage', 'privacy.assign',
                'support.view', 'support.manage', 'support.assign', 'support.reply', 'support.notes.manage', 'contact_history.view',
            ],
            'security-administrator' => [
                'admin.access', 'admins.view', 'roles.view', 'sessions.view', 'sessions.revoke', 'audit.view', 'security.view',
                'users.view', 'users.sessions.view', 'users.sessions.revoke', 'users.devices.view', 'users.devices.manage',
                'sos.view', 'sos.sensitive.audit', 'reports.view', 'appeals.view', 'risk.view', 'risk.manage',
                'privacy.view', 'contact_history.view',
            ],
            'support-agent' => [
                'admin.access', 'users.view', 'users.sessions.view', 'users.devices.view', 'users.notes.manage', 'circles.view',
                'reports.view', 'appeals.view', 'risk.view',
                'privacy.view', 'support.view', 'support.manage', 'support.assign', 'support.reply', 'support.notes.manage', 'contact_history.view',
            ],
            'moderator' => ['admin.access', 'users.view', 'circles.view', 'users.notes.manage', 'circles.notes.manage', 'sos.view', 'reports.view', 'reports.review', 'reports.assign', 'reports.notes.manage', 'reports.enforce', 'appeals.view', 'appeals.assign', 'appeals.review', 'appeals.second_review', 'risk.view', 'risk.manage'],
            'safety-operator' => [
                'admin.access', 'users.view', 'users.devices.view', 'circles.view',
                'sos.view', 'sos.manage', 'reports.view', 'reports.review', 'reports.assign', 'risk.view',
            ],
            'senior-safety-operator' => [
                'admin.access', 'users.view', 'users.devices.view', 'circles.view', 'audit.view',
                'sos.view', 'sos.manage', 'sos.export', 'sos.location.access',
                'sos.recordings.access', 'sos.sensitive.audit', 'reports.view', 'reports.review', 'reports.assign', 'reports.notes.manage', 'reports.enforce', 'appeals.view', 'appeals.assign', 'appeals.review', 'appeals.second_review', 'risk.view', 'risk.manage',
            ],
            'compliance-officer' => [
                'admin.access', 'users.view', 'users.sessions.view', 'users.devices.view', 'circles.view', 'audit.view',
                'sos.view', 'sos.sensitive.audit', 'reports.view', 'appeals.view', 'risk.view',
                'privacy.view', 'privacy.manage', 'privacy.assign', 'privacy.identity.verify',
                'privacy.exports.manage', 'privacy.exports.deliver', 'privacy.deletions.manage',
                'support.view', 'contact_history.view',
            ],
            'read-only' => [
                'admin.access', 'admins.view', 'roles.view', 'audit.view', 'users.view', 'users.sessions.view', 'users.devices.view',
                'circles.view', 'sos.view', 'reports.view', 'appeals.view', 'risk.view',
                'privacy.view', 'support.view', 'contact_history.view',
            ],
            default => ['admin.access'],
        };

        return AdminPermission::query()->whereIn('slug', $slugs)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
