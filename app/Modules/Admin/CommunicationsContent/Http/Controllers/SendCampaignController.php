<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\CommunicationCampaign;
use App\Modules\Admin\CommunicationsContent\Services\CampaignService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SendCampaignController
{
    public function __invoke(Request $request, string $campaignId, CampaignService $service, AdminAuditLogger $audit): JsonResponse
    {
        $c = CommunicationCampaign::query()->find($campaignId);
        if (! $c) {
            return AdminApiResponse::error($request, 'Campaign not found.', 'CAMPAIGN_NOT_FOUND', 404);
        } $c = $service->send($c, $request->user(), $request->attributes->get('admin_session'));
        $audit->write('communications.campaign.sent', $request->user(), $request->attributes->get('admin_session'), 'communication_campaign', $c->id, after: ['status' => $c->status, 'stats' => $c->stats], request: $request);

        return AdminApiResponse::success($request, $c->toArray());
    }
}
