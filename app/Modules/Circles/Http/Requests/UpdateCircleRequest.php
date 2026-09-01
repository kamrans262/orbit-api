<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class UpdateCircleRequest extends ApiFormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
