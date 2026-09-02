<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\LegalDocument;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListLegalDocumentsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $d = $request->validate(['status' => ['nullable', 'string', 'max:24'], 'document_type' => ['nullable', 'string', 'max:40'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $q = LegalDocument::query()->latest();
        if (! empty($d['status'])) {
            $q->where('status', $d['status']);
        } if (! empty($d['document_type'])) {
            $q->where('document_type', $d['document_type']);
        } $p = $q->paginate((int) ($d['per_page'] ?? 25));

        return AdminApiResponse::success($request, ['items' => $p->items(), 'pagination' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }
}
