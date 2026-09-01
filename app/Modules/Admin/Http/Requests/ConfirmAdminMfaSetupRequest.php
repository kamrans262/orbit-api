<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class ConfirmAdminMfaSetupRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'setup_token' => ['required', 'string', 'min:40', 'max:512'],
            'code' => ['required', 'digits:6'],
        ];
    }
}
