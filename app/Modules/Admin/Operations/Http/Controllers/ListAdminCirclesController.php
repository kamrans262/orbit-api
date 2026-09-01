<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Modules\Admin\Operations\Http\Requests\ListAdminCirclesRequest;
use App\Modules\Admin\Operations\Services\AdminCircleDirectoryService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAdminCirclesController
{
    public function __invoke(ListAdminCirclesRequest $request, AdminCircleDirectoryService $service): JsonResponse
    {
        $page = $service->paginate($request->validated());

        return AdminApiResponse::success($request, [
            'items' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
