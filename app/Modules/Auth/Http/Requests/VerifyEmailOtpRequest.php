<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\Support\EmailNormalizer;
use App\Support\Http\Requests\ApiFormRequest;

final class VerifyEmailOtpRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge([
                'email' => EmailNormalizer::normalize($this->string('email')->toString()),
            ]);
        }

        if (! $this->filled('device_name')) {
            $this->merge(['device_name' => 'Orbit Mobile']);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
