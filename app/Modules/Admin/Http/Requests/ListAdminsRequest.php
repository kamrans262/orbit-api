<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Validation\Rule;

final class ListAdminsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['invited', 'mfa_setup', 'active', 'disabled'])],
            'role' => ['nullable', 'string', 'max:120', 'exists:admin_roles,slug'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
