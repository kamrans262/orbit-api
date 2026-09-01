<?php

declare(strict_types=1);

namespace App\Modules\Activity\Http\Requests;

use App\Modules\Activity\Enums\ActivityReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReportActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ActivityReportReason::class)],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }
}
