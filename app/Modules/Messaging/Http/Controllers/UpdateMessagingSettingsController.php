<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MessagingPreference;
use App\Modules\Messaging\Http\Requests\UpdateMessagingSettingsRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateMessagingSettingsController extends Controller
{
    public function __invoke(UpdateMessagingSettingsRequest $request): JsonResponse
    {
        $preference = MessagingPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['read_receipts_enabled' => $request->boolean('read_receipts_enabled')],
        );

        return ApiResponse::success([
            'read_receipts_enabled' => $preference->read_receipts_enabled,
        ], 'Messaging settings updated.');
    }
}
