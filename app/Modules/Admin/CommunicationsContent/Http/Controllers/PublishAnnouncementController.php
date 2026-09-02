<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\Announcement;
use App\Modules\Admin\CommunicationsContent\Services\CommunicationAuthorizationService;
use App\Modules\Admin\CommunicationsContent\Services\PublicationService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublishAnnouncementController
{
    public function __invoke(
        Request $request,
        string $announcementId,
        PublicationService $service,
        CommunicationAuthorizationService $authorization,
        AdminAuditLogger $audit,
    ): JsonResponse {
        $announcement = Announcement::query()->find($announcementId);
        if (! $announcement) {
            return AdminApiResponse::error($request, 'Announcement not found.', 'CONTENT_NOT_FOUND', 404);
        }

        $sensitive = in_array($announcement->type, ['security', 'disruption'], true)
            || $announcement->priority === 'highest';
        if ($sensitive) {
            $authorization->assertEmergency($request->user(), $request->attributes->get('admin_session'));
        }

        $published = $service->publish('announcement', $announcementId, $request->user());
        $audit->write(
            'announcements.published',
            $request->user(),
            $request->attributes->get('admin_session'),
            'announcement',
            $announcementId,
            after: ['status' => 'published', 'sensitive' => $sensitive],
            request: $request,
        );

        return AdminApiResponse::success($request, $published->toArray());
    }
}
