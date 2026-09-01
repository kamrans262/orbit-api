<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class JoinCircleRequest extends ApiFormRequest
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
            'code' => ['required', 'string', 'min:6', 'max:32', 'alpha_num'],
        ];
    }
}
