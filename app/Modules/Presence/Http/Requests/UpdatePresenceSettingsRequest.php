<?php

declare(strict_types=1);

namespace App\Modules\Presence\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class UpdatePresenceSettingsRequest extends ApiFormRequest
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
            'global_ghost_mode' => ['required', 'boolean'],
        ];
    }
}
