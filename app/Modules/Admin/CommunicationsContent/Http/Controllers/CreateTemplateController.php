<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\CommunicationTemplate;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateTemplateController
{
    public function __invoke(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $d = $request->validate(['slug' => ['required', 'alpha_dash', 'max:80', 'unique:communication_templates,slug'], 'channel' => ['required', Rule::in(['push', 'in_app', 'email', 'sms', 'system_banner', 'account_notice', 'security_alert', 'subscription', 'safety'])], 'category' => ['nullable', 'string', 'max:40'], 'variables' => ['nullable', 'array', 'max:50']]);
        $m = CommunicationTemplate::query()->create([...$d, 'slug' => strtolower($d['slug']), 'category' => $d['category'] ?? 'general', 'status' => 'draft', 'created_by_admin_id' => $request->user()->id]);
        $audit->write('templates.created', $request->user(), $request->attributes->get('admin_session'), 'communication_template', $m->id, after: ['slug' => $m->slug, 'channel' => $m->channel], request: $request);

        return AdminApiResponse::success($request, $m->toArray(), 201);
    }
}
