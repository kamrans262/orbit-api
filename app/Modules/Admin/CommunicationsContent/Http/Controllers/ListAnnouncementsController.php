<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\Announcement;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAnnouncementsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $d = $request->validate(['status' => ['nullable', 'string', 'max:24'], 'type' => ['nullable', 'string', 'max:40'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $q = Announcement::query()->latest();
        if (! empty($d['status'])) {
            $q->where('status', $d['status']);
        } if (! empty($d['type'])) {
            $q->where('type', $d['type']);
        } $p = $q->paginate((int) ($d['per_page'] ?? 25));

        return AdminApiResponse::success($request, ['items' => $p->items(), 'pagination' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }
}
