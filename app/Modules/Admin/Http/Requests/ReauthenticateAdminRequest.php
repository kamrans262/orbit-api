<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class ReauthenticateAdminRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:1024'],
            'code' => ['required', 'string', 'min:6', 'max:32'],
        ];
    }
}
