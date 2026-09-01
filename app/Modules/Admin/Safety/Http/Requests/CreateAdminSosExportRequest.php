<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class CreateAdminSosExportRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'format' => ['required', 'in:json'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
