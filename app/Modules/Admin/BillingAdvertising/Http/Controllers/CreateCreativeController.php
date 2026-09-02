<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateCreativeController
{
    public function __invoke(Request $request, string $campaignId, AdminAuditLogger $audit): JsonResponse
    {
        $c = AdCampaign::query()->find($campaignId);
        if ($c === null) {
            return AdminApiResponse::error($request, 'Campaign not found.', 'AD_CAMPAIGN_NOT_FOUND', 404);
        }$data = $request->validate(['type' => ['required', 'in:card,map_pin'], 'title' => ['required', 'string', 'max:120'], 'body' => ['nullable', 'string', 'max:500'], 'media_ref' => ['nullable', 'string', 'max:255'], 'deep_link' => ['nullable', 'string', 'max:500'], 'cta' => ['nullable', 'string', 'max:80'], 'metadata' => ['nullable', 'array']]);
        $creative = AdCreative::query()->create([...$data, 'campaign_id' => $c->id, 'status' => 'active']);
        $audit->write('advertising.creative.created', $request->user(), $request->attributes->get('admin_session'), 'ad_creative', $creative->id, after: ['campaign_id' => $c->id, 'type' => $creative->type], request: $request);

        return AdminApiResponse::success($request, ['id' => $creative->id], 201);
    }
}
