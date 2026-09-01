<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class ListAdminUsersRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'account_status' => ['nullable', 'in:active,suspended,deleted'],
            'platform' => ['nullable', 'in:ios,android,web'],
            'risk_level' => ['nullable', 'in:normal,watch,elevated,high'],
            'verified' => ['nullable', 'boolean'],
            'registered_from' => ['nullable', 'date'],
            'registered_to' => ['nullable', 'date', 'after_or_equal:registered_from'],
            'last_active_from' => ['nullable', 'date'],
            'last_active_to' => ['nullable', 'date', 'after_or_equal:last_active_from'],
            'deletion_state' => ['nullable', 'in:none,scheduled,completed'],
            'has_sos' => ['nullable', 'boolean'],
            'min_circle_count' => ['nullable', 'integer', 'min:0'],
            'max_circle_count' => ['nullable', 'integer', 'min:0', 'gte:min_circle_count'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
