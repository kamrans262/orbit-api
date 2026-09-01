<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Requests;

use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

final class UpdateCircleMemberRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'sometimes',
                'string',
                Rule::in([
                    CircleRole::Admin->value,
                    CircleRole::Member->value,
                    CircleRole::Restricted->value,
                ]),
            ],
            'location_mode' => ['sometimes', 'string', Rule::enum(LocationMode::class)],
            'can_ping' => ['sometimes', 'boolean'],
            'can_message' => ['sometimes', 'boolean'],
            'can_view_moments' => ['sometimes', 'boolean'],
            'activity_visibility' => ['sometimes', 'boolean'],
        ];
    }
}
