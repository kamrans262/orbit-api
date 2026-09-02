<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\CommunicationTemplate;
use App\Modules\Admin\CommunicationsContent\Services\TemplateRenderingService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PreviewTemplateController
{
    public function __invoke(Request $request, string $templateId, TemplateRenderingService $service): JsonResponse
    {
        $d = $request->validate(['locale' => ['nullable', 'string', 'max:12'], 'variables' => ['nullable', 'array']]);
        $m = CommunicationTemplate::query()->find($templateId);
        if (! $m) {
            return AdminApiResponse::error($request, 'Template not found.', 'TEMPLATE_NOT_FOUND', 404);
        }

return AdminApiResponse::success($request, $service->render($m, (string) ($d['locale'] ?? 'en'), $d['variables'] ?? []));
    }
}
