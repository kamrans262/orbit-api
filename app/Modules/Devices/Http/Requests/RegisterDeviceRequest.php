<?php

declare(strict_types=1);

namespace App\Modules\Devices\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

final class RegisterDeviceRequest extends ApiFormRequest
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
            'client_device_id' => ['required', 'string', 'max:128'],
            'platform' => ['required', 'string', Rule::in(['android', 'ios', 'web'])],
            'name' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:100'],
            'public_identity_key' => ['nullable', 'string', 'max:12000'],
            'push_token' => ['nullable', 'string', 'max:4096'],
        ];
    }
}
