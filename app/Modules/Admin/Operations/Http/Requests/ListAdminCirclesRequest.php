<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class ListAdminCirclesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,archived,frozen,removed'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'expires_from' => ['nullable', 'date'],
            'expires_to' => ['nullable', 'date', 'after_or_equal:expires_from'],
            'has_sos' => ['nullable', 'boolean'],
            'min_member_count' => ['nullable', 'integer', 'min:0'],
            'max_member_count' => ['nullable', 'integer', 'min:0', 'gte:min_member_count'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
