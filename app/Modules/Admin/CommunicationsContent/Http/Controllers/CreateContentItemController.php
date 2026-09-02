<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\ContentItem;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateContentItemController
{
    public function __invoke(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $d = $request->validate(['type' => ['required', Rule::in(['faq', 'help', 'safety', 'support_article', 'legal_link', 'onboarding', 'release_announcement'])], 'slug' => ['required', 'alpha_dash', 'max:120', 'unique:content_items,slug'], 'regions' => ['nullable', 'array'], 'scheduled_at' => ['nullable', 'date']]);
        $m = ContentItem::query()->create([...$d, 'slug' => strtolower($d['slug']), 'status' => 'draft', 'created_by_admin_id' => $request->user()->id]);
        $audit->write('content.created', $request->user(), $request->attributes->get('admin_session'), 'content', $m->id, request: $request);

        return AdminApiResponse::success($request, $m->toArray(), 201);
    }
}
