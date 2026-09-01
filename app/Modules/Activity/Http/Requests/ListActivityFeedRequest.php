<?php

declare(strict_types=1);

namespace App\Modules\Activity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListActivityFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
        ];
    }
}
