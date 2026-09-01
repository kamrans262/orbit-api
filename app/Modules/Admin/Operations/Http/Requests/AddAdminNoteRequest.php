<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class AddAdminNoteRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:1', 'max:5000']];
    }
}
