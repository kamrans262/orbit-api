<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;
use Illuminate\Validation\Rule;

final class UpdateAdminCircleControlsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'feature_restrictions' => ['present', 'array', 'max:20'],
            'feature_restrictions.*' => ['string', Rule::in((array) config('orbit_admin_operations.allowed_circle_features', []))],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
