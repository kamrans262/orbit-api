<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Modules\Admin\CommunicationsContent\Services\PublicationService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublishTemplateController
{
    public function __invoke(Request $request, string $templateId, PublicationService $service, AdminAuditLogger $audit): JsonResponse
    {
        $m = $service->publish('template', $templateId, $request->user());
        $audit->write('templates.published', $request->user(), $request->attributes->get('admin_session'), 'communication_template', $templateId, after: ['status' => 'published'], request: $request);

        return AdminApiResponse::success($request, $m->toArray());
    }
}
