<?php

declare(strict_types=1);

namespace App\Modules\Admin\BackendCompletion\Services;

use App\Models\AdminSavedView;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

final class AdminSavedViewService
{
    public const array MODULES = [
        'users', 'circles', 'sos', 'reports', 'appeals', 'risk', 'support', 'privacy',
        'subscriptions', 'payments', 'advertising', 'communications', 'analytics', 'security',
        'system', 'audit',
    ];

    private const array MODULE_PERMISSIONS = [
        'users' => 'users.view',
        'circles' => 'circles.view',
        'sos' => 'sos.view',
        'reports' => 'reports.view',
        'appeals' => 'appeals.view',
        'risk' => 'risk.view',
        'support' => 'support.view',
        'privacy' => 'privacy.view',
        'subscriptions' => 'subscriptions.view',
        'payments' => 'payments.view',
        'advertising' => 'advertising.view',
        'communications' => 'communications.view',
        'analytics' => 'analytics.view',
        'security' => 'security.view',
        'system' => 'operations.view',
        'audit' => 'audit.view',
    ];

    public function visible(AdminUser $admin, ?string $module): Collection
    {
        $allowedModules = collect(self::MODULE_PERMISSIONS)
            ->filter(fn (string $permission): bool => $admin->hasPermission($permission))
            ->keys()
            ->all();

        if ($module !== null && ! in_array($module, $allowedModules, true)) {
            return new Collection;
        }

        return AdminSavedView::query()
            ->whereIn('module', $allowedModules)
            ->where(function ($query) use ($admin): void {
                $query->where('admin_user_id', $admin->id)->orWhere('scope', 'team');
            })
            ->when($module, fn ($query) => $query->where('module', $module))
            ->orderBy('module')
            ->orderBy('name')
            ->get();
    }

    public function canAccessModule(AdminUser $admin, string $module): bool
    {
        return isset(self::MODULE_PERMISSIONS[$module])
            && $admin->hasPermission(self::MODULE_PERMISSIONS[$module]);
    }

    public function create(AdminUser $admin, array $data): AdminSavedView
    {
        $this->assertModule((string) $data['module']);
        if (! $this->canAccessModule($admin, (string) $data['module'])) {
            throw new InvalidArgumentException('You are not authorized to access that saved-view module.');
        }

        return AdminSavedView::query()->create([
            'admin_user_id' => $admin->id,
            'name' => $data['name'],
            'module' => $data['module'],
            'scope' => $data['scope'] ?? 'personal',
            'filters' => $this->sanitize($data['filters'] ?? []),
            'columns' => $this->sanitize($data['columns'] ?? []),
            'sort' => $this->sanitize($data['sort'] ?? []),
        ]);
    }

    public function update(AdminSavedView $view, AdminUser $admin, array $data): AdminSavedView
    {
        if ((int) $view->admin_user_id !== (int) $admin->id) {
            throw new InvalidArgumentException('Only the owner can modify this saved view.');
        }
        $effectiveModule = (string) ($data['module'] ?? $view->module);
        $this->assertModule($effectiveModule);
        if (! $this->canAccessModule($admin, $effectiveModule)) {
            throw new InvalidArgumentException('You are not authorized to access that saved-view module.');
        }
        $updates = collect($data)->only(['name', 'module', 'scope'])->all();
        foreach (['filters', 'columns', 'sort'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $this->sanitize($data[$field] ?? []);
            }
        }
        $view->fill($updates)->save();

        return $view->refresh();
    }

    public function delete(AdminSavedView $view, AdminUser $admin): void
    {
        if ((int) $view->admin_user_id !== (int) $admin->id) {
            throw new InvalidArgumentException('Only the owner can delete this saved view.');
        }
        $view->delete();
    }

    private function assertModule(string $module): void
    {
        if (! in_array($module, self::MODULES, true)) {
            throw new InvalidArgumentException('Unsupported saved-view module.');
        }
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null) {
            $normalized = strtolower($key);
            foreach (['token', 'authorization', 'password', 'secret', 'otp', 'recovery_code', 'ciphertext', 'plaintext', 'private_key', 'recording_ref', 'latitude', 'longitude'] as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    return '[REDACTED]';
                }
            }
        }
        if (! is_array($value)) {
            return is_string($value) ? mb_substr($value, 0, 500) : $value;
        }
        $clean = [];
        foreach ($value as $childKey => $childValue) {
            $clean[$childKey] = $this->sanitize($childValue, is_string($childKey) ? $childKey : null);
        }

        return $clean;
    }
}
