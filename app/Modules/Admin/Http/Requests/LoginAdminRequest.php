<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class LoginAdminRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ];
    }
}
