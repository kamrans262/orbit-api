<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class CreateCircleInviteRequest extends ApiFormRequest
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
            'expires_in_minutes' => ['sometimes', 'integer', 'min:15', 'max:10080'],
            'max_uses' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
