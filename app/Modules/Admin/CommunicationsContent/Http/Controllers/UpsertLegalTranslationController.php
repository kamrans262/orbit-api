<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Modules\Admin\CommunicationsContent\Services\PublicationService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class UpsertLegalTranslationController
{
    public function __invoke(Request $request, string $legalId, string $locale, PublicationService $service, AdminAuditLogger $audit): JsonResponse
    {
        $d = $request->validate(['status' => ['nullable', Rule::in(['draft', 'review'])], 'title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string', 'max:200000']]);
        $m = $service->translation('legal', $legalId, $locale, $d, $request->user());
        $audit->write('legal.translation.updated', $request->user(), $request->attributes->get('admin_session'), 'legal', $legalId, metadata: ['locale' => $locale, 'status' => $m->status], request: $request);

        return AdminApiResponse::success($request, $m->toArray());
    }
}
