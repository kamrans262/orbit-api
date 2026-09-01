<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class ListAdminLoginEventsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'admin_user_id' => ['nullable', 'integer', 'exists:admin_users,id'],
            'suspicious' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
