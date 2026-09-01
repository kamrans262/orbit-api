<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class UpdateAdminRoleRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
