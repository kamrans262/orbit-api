<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\CommunicationCampaign;
use App\Modules\Admin\CommunicationsContent\Services\CampaignService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListCampaignsController
{
    public function __invoke(Request $request, CampaignService $service): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'max:24'], 'channel' => ['nullable', 'string', 'max:24'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = CommunicationCampaign::query()->latest('created_at');
        foreach (['status', 'channel'] as $key) {
            if (! empty($data[$key])) {
                $query->where($key, $data[$key]);
            }
        }
        $page = $query->paginate((int) ($data['per_page'] ?? 25));

        return AdminApiResponse::success($request, ['items' => collect($page->items())->map(fn (CommunicationCampaign $c): array => [...$c->only(['id', 'name', 'channel', 'category', 'priority', 'status', 'is_emergency', 'scheduled_at', 'sent_at']), 'stats' => $service->stats($c)])->all(), 'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }
}
