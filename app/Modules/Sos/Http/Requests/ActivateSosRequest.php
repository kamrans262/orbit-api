<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ActivateSosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'circle_id' => ['required', 'string', 'max:64'],
            'recording_ref' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'location_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }
}
