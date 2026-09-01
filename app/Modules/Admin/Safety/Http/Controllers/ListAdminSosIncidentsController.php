<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Controllers;

use App\Modules\Admin\Safety\Http\Requests\ListAdminSosRequest;
use App\Modules\Admin\Safety\Services\AdminSosDirectoryService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAdminSosIncidentsController
{
    public function __invoke(ListAdminSosRequest $request, AdminSosDirectoryService $service): JsonResponse
    {
        $page = $service->paginate($request->validated());

        return AdminApiResponse::success($request, [
            'items' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
