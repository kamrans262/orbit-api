<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\AdCampaign;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateCampaignController
{
    public function __invoke(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['advertiser_id' => ['required', 'uuid', 'exists:advertisers,id'], 'name' => ['required', 'string', 'max:140'], 'placement' => ['required', 'in:feed_card,map_pin'], 'status' => ['nullable', 'in:draft,scheduled,active,paused,ended'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'targeting' => ['nullable', 'array'], 'targeting.countries' => ['nullable', 'array'], 'targeting.countries.*' => ['string', 'size:2'], 'targeting.platforms' => ['nullable', 'array'], 'targeting.platforms.*' => ['in:ios,android,web'], 'targeting.plans' => ['nullable', 'array'], 'targeting.plans.*' => ['in:free,lite,plus'], 'impression_cap_per_user' => ['nullable', 'integer', 'min:1', 'max:100000'], 'budget_minor' => ['nullable', 'integer', 'min:0'], 'currency' => ['nullable', 'string', 'size:3'], 'priority' => ['nullable', 'integer', 'min:0', 'max:65535']]);
        $c = AdCampaign::query()->create([...$data, 'status' => $data['status'] ?? 'draft', 'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null, 'created_by_admin_id' => $request->user()->id]);
        $audit->write('advertising.campaign.created', $request->user(), $request->attributes->get('admin_session'), 'ad_campaign', $c->id, after: ['status' => $c->status, 'placement' => $c->placement], request: $request);

        return AdminApiResponse::success($request, ['id' => $c->id], 201);
    }
}
