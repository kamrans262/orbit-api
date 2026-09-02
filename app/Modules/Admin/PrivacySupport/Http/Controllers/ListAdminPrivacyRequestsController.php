<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Modules\Admin\PrivacySupport\Services\PrivacyDirectoryService;
use App\Modules\Admin\PrivacySupport\Services\PrivacyPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdminPrivacyRequestsController
{
    public function __invoke(Request $request, PrivacyDirectoryService $directory, PrivacyPresenter $presenter): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'max:32'],
            'identity_status' => ['nullable', 'string', 'max:32'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'assigned_admin_id' => ['nullable', 'integer', 'min:1'],
            'unassigned' => ['nullable', 'boolean'],
            'overdue' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $directory->requests($filters);
        $data = collect($page->items())->map(fn (PrivacyRequest $row): array => $presenter->request($row))->all();

        return AdminApiResponse::success($request, [
            'items' => $data,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
