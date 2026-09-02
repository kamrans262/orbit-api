<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\ContentItem;
use App\Modules\Admin\CommunicationsContent\Services\PublicationService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublishContentItemController
{
    public function __invoke(Request $request, string $contentId, PublicationService $service, AdminAuditLogger $audit): JsonResponse
    {
        $item = ContentItem::query()->find($contentId);
        if (! $item) {
            return AdminApiResponse::error($request, 'Content not found.', 'CONTENT_NOT_FOUND', 404);
        }

        $item = $service->scheduleContent($item, $request->user());
        $action = $item->status === 'scheduled' ? 'content.scheduled' : 'content.published';
        $audit->write(
            $action,
            $request->user(),
            $request->attributes->get('admin_session'),
            'content',
            $contentId,
            after: ['status' => $item->status, 'scheduled_at' => $item->scheduled_at?->toIso8601String()],
            request: $request,
        );

        return AdminApiResponse::success($request, $item->toArray());
    }
}
