<?php

declare(strict_types=1);

namespace App\Modules\Profile\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class UpdateProfileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
            'locale' => ['sometimes', 'required', 'string', 'max:10', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z]{2,4})?$/'],
        ];
    }
}
