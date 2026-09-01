<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCircleNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'muted_until' => ['nullable', 'date'],
            'silent' => ['sometimes', 'boolean'],
        ];
    }
}
