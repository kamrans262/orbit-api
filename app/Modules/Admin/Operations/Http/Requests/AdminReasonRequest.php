<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class AdminReasonRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}
