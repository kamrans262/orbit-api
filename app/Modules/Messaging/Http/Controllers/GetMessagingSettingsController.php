<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MessagingPreference;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetMessagingSettingsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $enabled = MessagingPreference::query()
            ->where('user_id', $request->user()->id)
            ->value('read_receipts_enabled');

        return ApiResponse::success([
            'read_receipts_enabled' => $enabled ?? true,
        ], 'Messaging settings retrieved.');
    }
}
