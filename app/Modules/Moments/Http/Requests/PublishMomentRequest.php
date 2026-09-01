<?php

declare(strict_types=1);

namespace App\Modules\Moments\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

final class PublishMomentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'moment_id' => ['required', 'uuid'],
            'media_asset_id' => ['required', 'uuid'],
            'ttl_seconds' => [
                'nullable',
                'integer',
                'min:300',
                'max:'.max(300, (int) config('orbit_moments.max_ttl_seconds', 86400)),
            ],
        ];
    }
}
