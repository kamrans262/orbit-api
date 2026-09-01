<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class UpdateAdminSosClassificationRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'operational_status' => ['sometimes', 'in:open,monitoring,escalated,closed'],
            'internal_escalation_level' => ['sometimes', 'in:normal,elevated,critical'],
            'false_alarm' => ['sometimes', 'boolean'],
            'technical_failure' => ['sometimes', 'boolean'],
            'abuse_flag' => ['sometimes', 'boolean'],
            'operational_resolution' => ['nullable', 'string', 'max:500'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
