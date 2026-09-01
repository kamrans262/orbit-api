<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class AddAdminSosNoteRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:1', 'max:4000']];
    }
}
