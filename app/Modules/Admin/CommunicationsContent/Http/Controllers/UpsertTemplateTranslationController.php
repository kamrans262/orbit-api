<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Modules\Admin\CommunicationsContent\Services\PublicationService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class UpsertTemplateTranslationController
{
    public function __invoke(Request $request, string $templateId, string $locale, PublicationService $service, AdminAuditLogger $audit): JsonResponse
    {
        $d = $request->validate(['status' => ['nullable', Rule::in(['draft', 'review'])], 'subject' => ['nullable', 'string', 'max:180'], 'title' => ['nullable', 'string', 'max:180'], 'body' => ['required', 'string', 'max:20000']]);
        $m = $service->translation('template', $templateId, $locale, $d, $request->user());
        $audit->write('templates.translation.updated', $request->user(), $request->attributes->get('admin_session'), 'communication_template', $templateId, metadata: ['locale' => $locale, 'status' => $m->status], request: $request);

        return AdminApiResponse::success($request, $m->toArray());
    }
}
