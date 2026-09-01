<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class TypingIndicatorRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['is_typing' => ['required', 'boolean']];
    }
}
