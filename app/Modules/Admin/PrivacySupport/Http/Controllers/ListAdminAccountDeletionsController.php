<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Modules\Admin\PrivacySupport\Services\PrivacyDirectoryService;
use App\Modules\Admin\PrivacySupport\Services\PrivacyPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdminAccountDeletionsController
{
    public function __invoke(Request $request, PrivacyDirectoryService $directory, PrivacyPresenter $presenter): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:24'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'due' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $page = $directory->deletions($filters);

        return AdminApiResponse::success($request, [
            'items' => collect($page->items())->map(fn (AccountDeletionRequest $row): array => $presenter->deletion($row))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }
}
