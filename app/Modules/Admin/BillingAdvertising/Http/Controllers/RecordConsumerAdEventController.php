<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\AdCampaign;
use App\Modules\Admin\BillingAdvertising\Services\AdvertisingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecordConsumerAdEventController
{
    public function __invoke(Request $request, string $campaignId, AdvertisingService $ads): JsonResponse
    {
        $campaign = AdCampaign::query()->find($campaignId);
        abort_if($campaign === null, 404);
        $data = $request->validate(['event_type' => ['required', 'in:impression,click,hide'], 'creative_id' => ['nullable', 'uuid'], 'client_event_id' => ['nullable', 'string', 'max:100'], 'country' => ['nullable', 'string', 'size:2'], 'platform' => ['nullable', 'in:ios,android,web']]);
        $event = $ads->recordEvent($request->user(), $campaign, $data);

        return response()->json(['success' => true, 'data' => ['id' => $event->id, 'event_type' => $event->event_type]], 202);
    }
}
