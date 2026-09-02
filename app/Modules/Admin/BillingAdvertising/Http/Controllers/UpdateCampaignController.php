<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\AdCampaign;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UpdateCampaignController
{
    public function __invoke(Request $request, string $campaignId, AdminAuditLogger $audit): JsonResponse
    {
        $c = AdCampaign::query()->find($campaignId);
        if ($c === null) {
            return AdminApiResponse::error($request, 'Campaign not found.', 'AD_CAMPAIGN_NOT_FOUND', 404);
        }$data = $request->validate(['name' => ['sometimes', 'string', 'max:140'], 'status' => ['sometimes', 'in:draft,scheduled,active,paused,ended'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'], 'targeting' => ['nullable', 'array'], 'impression_cap_per_user' => ['nullable', 'integer', 'min:1'], 'budget_minor' => ['nullable', 'integer', 'min:0'], 'currency' => ['nullable', 'string', 'size:3'], 'priority' => ['nullable', 'integer', 'min:0', 'max:65535']]);
        $before = $c->only(['name', 'status', 'starts_at', 'ends_at', 'targeting']);
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }$c->fill($data)->save();
        $audit->write('advertising.campaign.updated', $request->user(), $request->attributes->get('admin_session'), 'ad_campaign', $c->id, before: $before, after: $c->only(['name', 'status', 'starts_at', 'ends_at', 'targeting']), request: $request);

        return AdminApiResponse::success($request, ['id' => $c->id, 'status' => $c->status]);
    }
}
