<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class ListAdminAuditLogsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'admin_user_id' => ['nullable', 'integer', 'exists:admin_users,id'],
            'action' => ['nullable', 'string', 'max:140'],
            'request_id' => ['nullable', 'string', 'max:80'],
            'target_type' => ['nullable', 'string', 'max:100'],
            'target_id' => ['nullable', 'string', 'max:160'],
            'result' => ['nullable', 'string', 'max:24'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
