<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\CommunicationCampaign;
use App\Modules\Admin\CommunicationsContent\Services\CampaignService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowCampaignController
{
    public function __invoke(Request $request, string $campaignId, CampaignService $service): JsonResponse
    {
        $c = CommunicationCampaign::query()->find($campaignId);
        if (! $c) {
            return AdminApiResponse::error($request, 'Campaign not found.', 'CAMPAIGN_NOT_FOUND', 404);
        }

return AdminApiResponse::success($request, [...$c->toArray(), 'stats' => $service->stats($c)]);
    }
}
