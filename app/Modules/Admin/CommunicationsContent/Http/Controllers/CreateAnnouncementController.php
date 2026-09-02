<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\Announcement;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateAnnouncementController
{
    public function __invoke(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $d = $request->validate(['type' => ['required', Rule::in(['maintenance', 'product', 'security', 'feature', 'policy', 'disruption'])], 'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'highest'])], 'dismissible' => ['nullable', 'boolean'], 'deep_link' => ['nullable', 'string', 'max:500'], 'audience' => ['required', 'array'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at']]);
        $m = Announcement::query()->create([...$d, 'status' => 'draft', 'priority' => $d['priority'] ?? 'normal', 'dismissible' => (bool) ($d['dismissible'] ?? true), 'created_by_admin_id' => $request->user()->id]);
        $audit->write('announcements.created', $request->user(), $request->attributes->get('admin_session'), 'announcement', $m->id, request: $request);

        return AdminApiResponse::success($request, $m->toArray(), 201);
    }
}
