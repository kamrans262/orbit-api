<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;

final class AdminSosSensitiveAccessRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'in:active_incident_support,post_incident_review,legal_compliance,technical_investigation'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
