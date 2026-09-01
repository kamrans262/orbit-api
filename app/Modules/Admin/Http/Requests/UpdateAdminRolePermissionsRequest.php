<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class UpdateAdminRolePermissionsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'permission_slugs' => ['required', 'array', 'min:1', 'max:100'],
            'permission_slugs.*' => ['required', 'string', 'distinct', 'exists:admin_permissions,slug'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
