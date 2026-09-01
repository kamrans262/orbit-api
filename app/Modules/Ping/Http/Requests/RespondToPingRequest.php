<?php

declare(strict_types=1);

namespace App\Modules\Ping\Http\Requests;

use App\Modules\Ping\Enums\PingResponseType;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

final class RespondToPingRequest extends ApiFormRequest
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
            'response_type' => ['required', Rule::enum(PingResponseType::class)],
        ];
    }
}
