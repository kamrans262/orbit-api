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

        ['name' => 'View Billing Plans', 'slug' => 'billing.plans.view', 'description' => 'View subscription plans, prices, and entitlements.', 'is_sensitive' => false],
        ['name' => 'Manage Billing Plans', 'slug' => 'billing.plans.manage', 'description' => 'Create and change plans, prices, entitlements, and promotions.', 'is_sensitive' => true],
        ['name' => 'View Subscriptions', 'slug' => 'subscriptions.view', 'description' => 'View consumer subscription and entitlement state.', 'is_sensitive' => false],
        ['name' => 'Manage Subscriptions', 'slug' => 'subscriptions.manage', 'description' => 'Change, grant, extend, cancel, restore, or promote subscriptions.', 'is_sensitive' => true],
        ['name' => 'View Payments', 'slug' => 'payments.view', 'description' => 'View payment ledger and provider reconciliation metadata.', 'is_sensitive' => false],
        ['name' => 'Reconcile Payments', 'slug' => 'payments.reconcile', 'description' => 'Record provider payment outcomes without storing payment credentials.', 'is_sensitive' => true],
        ['name' => 'View Refunds', 'slug' => 'refunds.view', 'description' => 'View refund requests and provider outcomes.', 'is_sensitive' => false],
        ['name' => 'Manage Refund Requests', 'slug' => 'refunds.manage', 'description' => 'Create finance refund requests within remaining refundable amounts.', 'is_sensitive' => true],
        ['name' => 'Approve Refunds', 'slug' => 'refunds.approve', 'description' => 'Approve, reject, or record provider results for refunds.', 'is_sensitive' => true],
        ['name' => 'View Revenue', 'slug' => 'revenue.view', 'description' => 'View revenue, MRR, ARR, refunds, and subscription distribution.', 'is_sensitive' => false],
        ['name' => 'View Advertising', 'slug' => 'advertising.view', 'description' => 'View advertisers, campaigns, creatives, and delivery state.', 'is_sensitive' => false],
        ['name' => 'Manage Advertising', 'slug' => 'advertising.manage', 'description' => 'Create and operate sponsored feed cards and map-pin campaigns.', 'is_sensitive' => true],

        ['name' => 'View Communications', 'slug' => 'communications.view', 'description' => 'View notification campaigns and provider-neutral delivery statistics.', 'is_sensitive' => false],
        ['name' => 'Manage Communications', 'slug' => 'communications.manage', 'description' => 'Create, preview, schedule, cancel, and send ordinary targeted communications.', 'is_sensitive' => false],
        ['name' => 'Send Emergency Communications', 'slug' => 'communications.emergency.send', 'description' => 'Authorize emergency or highly sensitive broadcasts after recent reauthentication.', 'is_sensitive' => true],
        ['name' => 'View Announcements', 'slug' => 'announcements.view', 'description' => 'View customer-facing announcements and localization state.', 'is_sensitive' => false],
        ['name' => 'Manage Announcements', 'slug' => 'announcements.manage', 'description' => 'Create, localize, review, publish, schedule, and retire announcements.', 'is_sensitive' => false],
        ['name' => 'View Templates', 'slug' => 'templates.view', 'description' => 'View communication templates, variables, and localized variants.', 'is_sensitive' => false],
        ['name' => 'Manage Templates', 'slug' => 'templates.manage', 'description' => 'Create, localize, review, preview, and publish communication templates.', 'is_sensitive' => false],
        ['name' => 'View Content', 'slug' => 'content.view', 'description' => 'View CMS help, safety, support, onboarding, and release content.', 'is_sensitive' => false],
        ['name' => 'Manage Content', 'slug' => 'content.manage', 'description' => 'Create, localize, review, schedule, and publish CMS content.', 'is_sensitive' => false],
        ['name' => 'View Legal Documents', 'slug' => 'legal.view', 'description' => 'View legal document versions, effective dates, regions, and acceptance policy.', 'is_sensitive' => false],
        ['name' => 'Manage Legal Documents', 'slug' => 'legal.manage', 'description' => 'Create and publish legal versions or reacceptance requirements.', 'is_sensitive' => true],
        ['name' => 'View Regional Configuration', 'slug' => 'regions.view', 'description' => 'View country and regional feature, legal, consent, SMS, emergency, and retention configuration.', 'is_sensitive' => false],
        ['name' => 'Manage Regional Configuration', 'slug' => 'regions.manage', 'description' => 'Modify country and regional operational configuration.', 'is_sensitive' => true],
        ['name' => 'View App Version Policy', 'slug' => 'app_versions.view', 'description' => 'View supported/recommended app-version policy by platform and environment.', 'is_sensitive' => false],
        ['name' => 'Manage App Version Policy', 'slug' => 'app_versions.manage', 'description' => 'Change minimum/recommended versions and soft/forced update policy.', 'is_sensitive' => true],
        ['name' => 'View Maintenance Controls', 'slug' => 'maintenance.view', 'description' => 'View global and service-specific maintenance windows.', 'is_sensitive' => false],
        ['name' => 'Manage Maintenance Controls', 'slug' => 'maintenance.manage', 'description' => 'Create, activate, cancel, and schedule maintenance windows without disabling SOS.', 'is_sensitive' => true],
        ['name' => 'View Analytics', 'slug' => 'analytics.view', 'description' => 'View product and business analytics aggregates.', 'is_sensitive' => false],
        ['name' => 'View Saved Reports', 'slug' => 'analytics.reports.view', 'description' => 'View personal and team analytics reports.', 'is_sensitive' => false],
        ['name' => 'Manage Saved Reports', 'slug' => 'analytics.reports.manage', 'description' => 'Create and schedule analytics reports.', 'is_sensitive' => false],
        ['name' => 'Create Analytics Exports', 'slug' => 'analytics.exports.create', 'description' => 'Generate audited analytics CSV exports.', 'is_sensitive' => false],
        ['name' => 'View Feature Flags', 'slug' => 'feature_flags.view', 'description' => 'View feature flag state and rollout targeting.', 'is_sensitive' => false],
        ['name' => 'Modify Feature Flags', 'slug' => 'feature_flags.modify', 'description' => 'Enable, disable, roll out, target, rollback, or archive feature flags.', 'is_sensitive' => true],
        ['name' => 'View Remote Config', 'slug' => 'remote_config.view', 'description' => 'View non-secret runtime configuration.', 'is_sensitive' => false],
        ['name' => 'Manage Remote Config', 'slug' => 'remote_config.manage', 'description' => 'Modify ordinary non-secret runtime configuration.', 'is_sensitive' => false],
        ['name' => 'Manage Critical Remote Config', 'slug' => 'remote_config.critical.manage', 'description' => 'Modify critical runtime configuration after reauthentication.', 'is_sensitive' => true],
        ['name' => 'View System Operations', 'slug' => 'operations.view', 'description' => 'View system health, telemetry, alerts, and operational state.', 'is_sensitive' => false],
        ['name' => 'Manage System Operations', 'slug' => 'operations.manage', 'description' => 'Acknowledge and manage operational alerts.', 'is_sensitive' => false],
        ['name' => 'Ingest Operational Telemetry', 'slug' => 'operations.telemetry.ingest', 'description' => 'Record trusted WebSocket operational metric snapshots.', 'is_sensitive' => false],
        ['name' => 'View Queues', 'slug' => 'queues.view', 'description' => 'View queue depth and safe failed-job metadata.', 'is_sensitive' => false],
        ['name' => 'Manage Queues', 'slug' => 'queues.manage', 'description' => 'Request safe retry or quarantine of failed queue jobs.', 'is_sensitive' => true],
        ['name' => 'View Incidents', 'slug' => 'incidents.view', 'description' => 'View platform error and incident records.', 'is_sensitive' => false],
        ['name' => 'Manage Incidents', 'slug' => 'incidents.manage', 'description' => 'Create, assign, annotate, and resolve platform incidents.', 'is_sensitive' => false],
        ['name' => 'View Integrations', 'slug' => 'integrations.view', 'description' => 'View masked provider/integration status.', 'is_sensitive' => false],
        ['name' => 'Manage Integrations', 'slug' => 'integrations.manage', 'description' => 'Update non-secret integration operational state.', 'is_sensitive' => true],
        ['name' => 'View Webhooks', 'slug' => 'webhooks.view', 'description' => 'View webhook delivery metadata without payloads or signing secrets.', 'is_sensitive' => false],
        ['name' => 'Retry Webhooks', 'slug' => 'webhooks.retry', 'description' => 'Request a controlled provider webhook retry.', 'is_sensitive' => true],
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
                'billing.plans.view', 'billing.plans.manage', 'subscriptions.view', 'subscriptions.manage', 'payments.view', 'payments.reconcile', 'refunds.view', 'refunds.manage', 'revenue.view', 'advertising.view', 'advertising.manage',
                'communications.view', 'communications.manage', 'announcements.view', 'announcements.manage', 'templates.view', 'templates.manage', 'content.view', 'content.manage', 'legal.view', 'regions.view', 'app_versions.view', 'maintenance.view',
                'analytics.view', 'analytics.reports.view', 'analytics.reports.manage', 'analytics.exports.create', 'feature_flags.view', 'remote_config.view', 'remote_config.manage', 'operations.view', 'queues.view', 'incidents.view', 'incidents.manage', 'integrations.view', 'webhooks.view',
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
                'billing.plans.view', 'subscriptions.view', 'payments.view', 'refunds.view', 'revenue.view', 'advertising.view',
                'communications.view', 'communications.manage', 'announcements.view', 'announcements.manage', 'templates.view', 'templates.manage', 'content.view', 'content.manage', 'legal.view', 'regions.view', 'app_versions.view', 'maintenance.view',
                'analytics.view', 'analytics.reports.view', 'analytics.reports.manage', 'analytics.exports.create', 'feature_flags.view', 'remote_config.view', 'remote_config.manage', 'operations.view', 'queues.view', 'incidents.view', 'incidents.manage', 'integrations.view', 'webhooks.view',
            ],
            'security-administrator' => [
                'admin.access', 'admins.view', 'roles.view', 'sessions.view', 'sessions.revoke', 'audit.view', 'security.view',
                'users.view', 'users.sessions.view', 'users.sessions.revoke', 'users.devices.view', 'users.devices.manage',
                'sos.view', 'sos.sensitive.audit', 'reports.view', 'appeals.view', 'risk.view', 'risk.manage',
                'privacy.view', 'contact_history.view',
                'communications.view', 'announcements.view', 'templates.view', 'content.view', 'legal.view', 'regions.view', 'app_versions.view', 'maintenance.view',
                'analytics.view', 'analytics.reports.view', 'analytics.reports.manage', 'analytics.exports.create', 'feature_flags.view', 'remote_config.view', 'operations.view', 'queues.view', 'incidents.view', 'integrations.view', 'webhooks.view',
            ],
            'support-agent' => [
                'admin.access', 'users.view', 'users.sessions.view', 'users.devices.view', 'users.notes.manage', 'circles.view',
                'reports.view', 'appeals.view', 'risk.view',
                'privacy.view', 'support.view', 'support.manage', 'support.assign', 'support.reply', 'support.notes.manage', 'contact_history.view',
                'announcements.view', 'templates.view', 'content.view', 'legal.view',
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
            'finance-manager' => [
                'admin.access', 'users.view', 'contact_history.view',
                'billing.plans.view', 'billing.plans.manage', 'subscriptions.view', 'subscriptions.manage',
                'payments.view', 'payments.reconcile', 'refunds.view', 'refunds.manage', 'refunds.approve', 'revenue.view',
            ],
            'marketing-manager' => [
                'admin.access', 'communications.view', 'communications.manage', 'announcements.view', 'announcements.manage',
                'templates.view', 'templates.manage', 'content.view', 'content.manage', 'legal.view', 'regions.view', 'app_versions.view', 'maintenance.view',
            ],
            'devops-operator' => [
                'admin.access', 'audit.view', 'communications.view', 'announcements.view', 'content.view', 'regions.view',
                'app_versions.view', 'app_versions.manage', 'maintenance.view', 'maintenance.manage',
                'analytics.view', 'analytics.reports.view', 'analytics.exports.create', 'feature_flags.view', 'feature_flags.modify', 'remote_config.view', 'remote_config.manage', 'remote_config.critical.manage', 'operations.view', 'operations.manage', 'operations.telemetry.ingest', 'queues.view', 'queues.manage', 'incidents.view', 'incidents.manage', 'integrations.view', 'integrations.manage', 'webhooks.view', 'webhooks.retry',
            ],
            'advertising-manager' => [
                'admin.access', 'advertising.view', 'advertising.manage', 'billing.plans.view', 'subscriptions.view',
                'announcements.view', 'content.view',
            ],
            'analyst' => [
                'admin.access', 'billing.plans.view', 'subscriptions.view', 'payments.view', 'refunds.view', 'revenue.view', 'advertising.view',
                'communications.view', 'announcements.view', 'templates.view', 'content.view', 'legal.view', 'regions.view', 'app_versions.view', 'maintenance.view',
                'analytics.view', 'analytics.reports.view', 'analytics.reports.manage', 'analytics.exports.create', 'feature_flags.view', 'remote_config.view', 'operations.view', 'queues.view', 'incidents.view', 'integrations.view', 'webhooks.view',
            ],
            'compliance-officer' => [
                'admin.access', 'users.view', 'users.sessions.view', 'users.devices.view', 'circles.view', 'audit.view',
                'sos.view', 'sos.sensitive.audit', 'reports.view', 'appeals.view', 'risk.view',
                'privacy.view', 'privacy.manage', 'privacy.assign', 'privacy.identity.verify',
                'privacy.exports.manage', 'privacy.exports.deliver', 'privacy.deletions.manage',
                'support.view', 'contact_history.view',
                'legal.view', 'legal.manage', 'regions.view', 'regions.manage', 'announcements.view', 'content.view',
            ],
            'read-only' => [
                'admin.access', 'admins.view', 'roles.view', 'audit.view', 'users.view', 'users.sessions.view', 'users.devices.view',
                'circles.view', 'sos.view', 'reports.view', 'appeals.view', 'risk.view',
                'privacy.view', 'support.view', 'contact_history.view',
                'billing.plans.view', 'subscriptions.view', 'payments.view', 'refunds.view', 'revenue.view', 'advertising.view',
                'communications.view', 'announcements.view', 'templates.view', 'content.view', 'legal.view', 'regions.view', 'app_versions.view', 'maintenance.view',
                'analytics.view', 'analytics.reports.view', 'feature_flags.view', 'remote_config.view', 'operations.view', 'queues.view', 'incidents.view', 'integrations.view', 'webhooks.view',
            ],
            default => ['admin.access'],
        };

        return AdminPermission::query()->whereIn('slug', $slugs)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
