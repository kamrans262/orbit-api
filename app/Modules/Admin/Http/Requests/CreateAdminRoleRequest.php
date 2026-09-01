<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class CreateAdminRoleRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:120', 'unique:admin_roles,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_slugs' => ['nullable', 'array', 'max:30'],
            'permission_slugs.*' => ['required', 'string', 'distinct', 'exists:admin_permissions,slug'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
