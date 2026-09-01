<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSosLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }
}
