<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\AdCampaign;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListCampaignsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'max:20'], 'placement' => ['nullable', 'in:feed_card,map_pin'], 'advertiser_id' => ['nullable', 'uuid']]);
        $q = AdCampaign::query()->latest();
        foreach (['status', 'placement', 'advertiser_id'] as $k) {
            if (isset($data[$k])) {
                $q->where($k, $data[$k]);
            }
        }

return AdminApiResponse::success($request, $q->paginate(50));
    }
}
