<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class SyncEncryptedMessagesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
