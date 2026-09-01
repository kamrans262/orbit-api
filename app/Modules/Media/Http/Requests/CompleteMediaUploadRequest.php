<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class CompleteMediaUploadRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxEnvelopeBytes = max(256, (int) config('orbit_media.max_key_envelope_bytes', 8192));

        return [
            'key_envelopes' => ['required', 'array', 'min:1'],
            'key_envelopes.*.recipient_device_id' => ['required', 'uuid', 'distinct'],
            'key_envelopes.*.algorithm' => ['required', 'string', 'max:100'],
            'key_envelopes.*.encrypted_key' => ['required', 'string', 'max:'.$maxEnvelopeBytes],
        ];
    }
}
