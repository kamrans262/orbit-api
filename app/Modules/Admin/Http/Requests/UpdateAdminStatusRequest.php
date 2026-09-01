<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Validation\Rule;

final class UpdateAdminStatusRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'disabled'])],
            'access_expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
