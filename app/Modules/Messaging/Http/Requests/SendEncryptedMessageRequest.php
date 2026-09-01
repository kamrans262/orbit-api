<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Requests;

use App\Modules\Messaging\Enums\MessageType;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

final class SendEncryptedMessageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'message_id' => ['required', 'uuid'],
            'sender_device_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(MessageType::class)],
            'client_sent_at' => ['nullable', 'date'],
            'envelopes' => ['required', 'array', 'min:1', 'max:500'],
            'envelopes.*.envelope_id' => ['required', 'uuid'],
            'envelopes.*.recipient_device_id' => ['required', 'uuid'],
            'envelopes.*.ciphertext' => ['required', 'string', 'max:131072'],
            'envelopes.*.encrypted_preview' => ['nullable', 'string', 'max:4096'],
        ];
    }
}
