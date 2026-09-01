<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class UpdateAdminRolesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'role_slugs' => ['required', 'array', 'min:1', 'max:8'],
            'role_slugs.*' => ['required', 'string', 'distinct', 'exists:admin_roles,slug'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
