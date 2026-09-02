<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\CommunicationTemplate;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListTemplatesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['channel' => ['nullable', 'string', 'max:24'], 'status' => ['nullable', 'string', 'max:24'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $q = CommunicationTemplate::query()->latest();
        foreach (['channel', 'status'] as $k) {
            if (! empty($data[$k])) {
                $q->where($k, $data[$k]);
            }
        } $p = $q->paginate((int) ($data['per_page'] ?? 25));

        return AdminApiResponse::success($request, ['items' => $p->items(), 'pagination' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }
}
