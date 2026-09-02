<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplate;
use App\Modules\Admin\CommunicationsContent\Services\TemplateRenderingService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateCampaignController
{
    public function __invoke(Request $request, TemplateRenderingService $renderer, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'channel' => ['required', Rule::in(['push', 'in_app', 'email', 'sms', 'system_banner'])],
            'category' => ['nullable', 'string', 'max:40'], 'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'highest'])], 'locale' => ['nullable', 'string', 'max:12'],
            'template_id' => ['nullable', 'uuid', 'exists:communication_templates,id'], 'variables' => ['nullable', 'array'], 'subject' => ['nullable', 'string', 'max:180'], 'title' => ['nullable', 'string', 'max:180'], 'body' => ['nullable', 'string', 'max:10000'], 'deep_link' => ['nullable', 'string', 'max:500'],
            'audience' => ['required', 'array'], 'audience.mode' => ['nullable', Rule::in(['all', 'selected'])], 'audience.user_ids' => ['nullable', 'array', 'max:5000'], 'audience.user_ids.*' => ['integer', 'exists:users,id'], 'audience.custom_user_ids' => ['nullable', 'array', 'max:5000'], 'audience.cohort_user_ids' => ['nullable', 'array', 'max:5000'], 'audience.plans' => ['nullable', 'array'], 'audience.countries' => ['nullable', 'array'], 'audience.platforms' => ['nullable', 'array'], 'audience.app_versions' => ['nullable', 'array'], 'is_emergency' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['template_id'])) {
            $rendered = $renderer->render(CommunicationTemplate::query()->findOrFail($data['template_id']), (string) ($data['locale'] ?? 'en'), $data['variables'] ?? []);
            $data['subject'] ??= $rendered['subject'];
            $data['title'] ??= $rendered['title'];
            $data['body'] ??= $rendered['body'];
        }
        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['body'] = trim((string) ($data['body'] ?? ''));
        if ($data['title'] === '' || $data['body'] === '') {
            return AdminApiResponse::error($request, 'Campaign title and body are required after template rendering.', 'CAMPAIGN_CONTENT_REQUIRED', 422);
        }
        $campaign = CommunicationCampaign::query()->create([...$data, 'category' => $data['category'] ?? 'product', 'priority' => $data['priority'] ?? 'normal', 'locale' => $data['locale'] ?? 'en', 'status' => 'draft', 'is_emergency' => (bool) ($data['is_emergency'] ?? false), 'created_by_admin_id' => $request->user()->id]);
        $audit->write('communications.campaign.created', $request->user(), $request->attributes->get('admin_session'), 'communication_campaign', $campaign->id, after: ['channel' => $campaign->channel, 'is_emergency' => $campaign->is_emergency], request: $request);

        return AdminApiResponse::success($request, $campaign->toArray(), 201);
    }
}
