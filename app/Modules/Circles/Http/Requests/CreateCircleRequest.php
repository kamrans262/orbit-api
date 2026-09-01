<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Requests;

use App\Modules\Circles\Enums\CircleType;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

final class CreateCircleRequest extends ApiFormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['sometimes', 'string', Rule::enum(CircleType::class)],
            'expires_at' => ['nullable', 'date', 'after:now', 'required_if:type,temporary'],
        ];
    }
}
