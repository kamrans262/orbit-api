<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class AddAdminTagRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return ['tag' => ['required', 'string', 'min:1', 'max:80', 'regex:/^[A-Za-z0-9 _.-]+$/']];
    }
}
