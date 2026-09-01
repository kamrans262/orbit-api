<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class ListAdminSosRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,resolved'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'circle_id' => ['nullable', 'uuid'],
            'escalation_min' => ['nullable', 'integer', 'min:0', 'max:3'],
            'escalation_max' => ['nullable', 'integer', 'min:0', 'max:3', 'gte:escalation_min'],
            'activated_from' => ['nullable', 'date'],
            'activated_to' => ['nullable', 'date', 'after_or_equal:activated_from'],
            'assigned_admin_id' => ['nullable', 'integer', 'min:1'],
            'unassigned' => ['nullable', 'boolean'],
            'operational_status' => ['nullable', 'in:open,monitoring,escalated,closed'],
            'false_alarm' => ['nullable', 'boolean'],
            'technical_failure' => ['nullable', 'boolean'],
            'abuse_flag' => ['nullable', 'boolean'],
            'fallback_used' => ['nullable', 'boolean'],
            'delivery_failures' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
