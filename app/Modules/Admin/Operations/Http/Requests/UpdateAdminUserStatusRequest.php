<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class UpdateAdminUserStatusRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:suspended,active'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ];
    }
}
