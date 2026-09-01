<?php

declare(strict_types=1);

namespace App\Modules\Presence\Http\Requests;

use App\Modules\Presence\Enums\MovementType;
use App\Modules\Presence\Enums\NetworkType;
use App\Modules\Presence\Enums\PresenceStatus;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

final class UpdatePresenceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'string', Rule::enum(PresenceStatus::class)],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'accuracy_meters' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'battery_level' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'is_charging' => ['sometimes', 'nullable', 'boolean'],
            'network_type' => ['sometimes', 'nullable', 'string', Rule::enum(NetworkType::class)],
            'movement_type' => ['sometimes', 'nullable', 'string', Rule::enum(MovementType::class)],
        ];
    }
}
