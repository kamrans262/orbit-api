<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Requests;

use App\Modules\Sos\Enums\SosResponderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RespondSosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in([
                SosResponderStatus::Engaged->value,
                SosResponderStatus::Declined->value,
            ])],
        ];
    }
}
