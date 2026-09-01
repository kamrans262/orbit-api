<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Operations\Http\Requests\AddAdminNoteRequest;
use App\Modules\Admin\Operations\Services\AdminAnnotationService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class AddAdminUserNoteController
{
    public function __invoke(AddAdminNoteRequest $request, int $userId, AdminAnnotationService $service): JsonResponse
    {
        if (! User::query()->whereKey($userId)->exists()) {
            return AdminApiResponse::error($request, 'User not found.', 'ADMIN_USER_NOT_FOUND', 404);
        }
        $note = $service->addNote('user', (string) $userId, (string) $request->validated('note'), AdminOperationContext::admin($request), AdminOperationContext::session($request), $request);

        return AdminApiResponse::success($request, ['id' => $note->id, 'note' => $note->note, 'created_at' => $note->created_at?->toIso8601String()], 201, 'Internal note added.');
    }
}
