<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Requests;

use App\Modules\Admin\Http\Requests\AdminFormRequest;
use Illuminate\Validation\Rule;

final class UpdateAdminUserControlsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'feature_restrictions' => ['present', 'array', 'max:20'],
            'feature_restrictions.*' => ['string', Rule::in((array) config('orbit_admin_operations.allowed_user_features', []))],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('orbit_admin_operations.max_user_rate_limit_per_minute', 600)],
            'require_reverification' => ['required', 'boolean'],
            'risk_level' => ['required', 'in:normal,watch,elevated,high'],
            'warning' => ['nullable', 'string', 'max:500'],
            'escalate_trust_safety' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
