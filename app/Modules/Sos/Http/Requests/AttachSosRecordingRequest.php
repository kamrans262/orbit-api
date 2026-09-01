<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AttachSosRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recording_ref' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }
}
