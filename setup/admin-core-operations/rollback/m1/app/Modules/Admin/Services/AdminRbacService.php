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
            ],
            'platform-administrator' => ['admin.access', 'admins.view', 'roles.view', 'sessions.view', 'audit.view'],
            'security-administrator' => ['admin.access', 'admins.view', 'roles.view', 'sessions.view', 'sessions.revoke', 'audit.view', 'security.view'],
            'read-only' => ['admin.access', 'admins.view', 'roles.view', 'audit.view'],
            default => ['admin.access'],
        };

        return AdminPermission::query()->whereIn('slug', $slugs)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
