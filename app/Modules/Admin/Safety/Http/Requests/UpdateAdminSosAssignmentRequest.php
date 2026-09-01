<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class UpdateAdminSosAssignmentRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'assigned_admin_id' => ['nullable', 'integer', 'exists:admin_users,id'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
