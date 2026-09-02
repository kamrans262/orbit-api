<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\CommunicationCampaign;
use App\Modules\Admin\CommunicationsContent\Services\CampaignService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ScheduleCampaignController
{
    public function __invoke(Request $request, string $campaignId, CampaignService $service, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);
        $c = CommunicationCampaign::query()->find($campaignId);
        if (! $c) {
            return AdminApiResponse::error($request, 'Campaign not found.', 'CAMPAIGN_NOT_FOUND', 404);
        } $c = $service->schedule($c, new \DateTimeImmutable($data['scheduled_at']), $request->user(), $request->attributes->get('admin_session'));
        $audit->write('communications.campaign.scheduled', $request->user(), $request->attributes->get('admin_session'), 'communication_campaign', $c->id, after: ['scheduled_at' => $c->scheduled_at?->toIso8601String()], request: $request);

        return AdminApiResponse::success($request,$c->toArray());
    }
}
