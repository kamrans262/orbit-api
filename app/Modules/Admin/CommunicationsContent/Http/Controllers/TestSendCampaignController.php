<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\CommunicationCampaign;
use App\Modules\Admin\CommunicationsContent\Services\CampaignService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TestSendCampaignController
{
    public function __invoke(Request $request, string $campaignId, CampaignService $service, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1', 'max:10'], 'user_ids.*' => ['integer', 'exists:users,id']]);
        $c = CommunicationCampaign::query()->find($campaignId);
        if (! $c) {
            return AdminApiResponse::error($request, 'Campaign not found.', 'CAMPAIGN_NOT_FOUND', 404);
        } $service->send($c, $request->user(), $request->attributes->get('admin_session'), $data['user_ids']);
        $audit->write('communications.campaign.test_sent', $request->user(), $request->attributes->get('admin_session'), 'communication_campaign', $c->id, metadata: ['recipient_count' => count($data['user_ids'])], request: $request);

        return AdminApiResponse::success($request, ['tested_user_ids' => $data['user_ids']], 202);
    }
}
