<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Requests;

final class RevokeAdminSessionRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}
