<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Modules\Admin\Operations\Http\Requests\ListAdminUsersRequest;
use App\Modules\Admin\Operations\Services\AdminUserDirectoryService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAdminUsersController
{
    public function __invoke(ListAdminUsersRequest $request, AdminUserDirectoryService $service): JsonResponse
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
