<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResolveSosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', Rule::in(['safe', 'false_alarm', 'help_arrived', 'other'])],
        ];
    }
}
