<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Modules\Admin\CommunicationsContent\Services\PublicationService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class UpsertAnnouncementTranslationController
{
    public function __invoke(Request $request, string $announcementId, string $locale, PublicationService $service, AdminAuditLogger $audit): JsonResponse
    {
        $d = $request->validate(['status' => ['nullable', Rule::in(['draft', 'review'])], 'title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string', 'max:20000']]);
        $m = $service->translation('announcement', $announcementId, $locale, $d, $request->user());
        $audit->write('announcements.translation.updated', $request->user(), $request->attributes->get('admin_session'), 'announcement', $announcementId, metadata: ['locale' => $locale, 'status' => $m->status], request: $request);

        return AdminApiResponse::success($request, $m->toArray());
    }
}
