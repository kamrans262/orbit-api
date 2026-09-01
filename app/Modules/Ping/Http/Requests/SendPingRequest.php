<?php

declare(strict_types=1);

namespace App\Modules\Ping\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class SendPingRequest extends ApiFormRequest
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
            'circle_id' => ['required', 'uuid'],
            'recipient_membership_id' => ['required', 'uuid'],
        ];
    }
}
