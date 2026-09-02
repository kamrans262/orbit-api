<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Modules\Admin\BillingAdvertising\Services\AdvertisingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListConsumerAdsController
{
    public function __invoke(Request $request, string $placement, AdvertisingService $ads): JsonResponse
    {
        abort_unless(in_array($placement, ['feed_card', 'map_pin'], true), 404);
        $data = $request->validate(['country' => ['nullable', 'string', 'size:2'], 'platform' => ['nullable', 'in:ios,android,web']]);
        $limit = $placement === 'feed_card' ? 3 : 20;

        return response()->json(['success' => true, 'data' => $ads->eligible($request->user(), $placement, $data['country'] ?? null, $data['platform'] ?? null, $limit)]);
    }
}
