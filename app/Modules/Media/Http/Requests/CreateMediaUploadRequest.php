<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use App\Modules\Media\Enums\MediaKind;
use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

final class CreateMediaUploadRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'uuid', 'unique:media_uploads,asset_id', 'unique:media_assets,id'],
            'uploader_device_id' => ['required', 'uuid'],
            'kind' => ['required', Rule::enum(MediaKind::class)],
            'content_type_hint' => ['nullable', 'string', 'max:150'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.config('orbit_media.max_size_bytes', 104857600)],
            'sha256_ciphertext' => ['required', 'string', 'size:64', 'regex:/^[a-fA-F0-9]{64}$/'],
        ];
    }
}
