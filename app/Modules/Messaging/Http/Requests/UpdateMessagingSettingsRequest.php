<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class UpdateMessagingSettingsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['read_receipts_enabled' => ['required', 'boolean']];
    }
}
