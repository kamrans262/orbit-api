<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\Circle;
use App\Modules\Admin\Operations\Http\Requests\AddAdminNoteRequest;
use App\Modules\Admin\Operations\Services\AdminAnnotationService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class AddAdminCircleNoteController
{
    public function __invoke(AddAdminNoteRequest $request, string $circleId, AdminAnnotationService $service): JsonResponse
    {
        if (! Circle::query()->whereKey($circleId)->exists()) {
            return AdminApiResponse::error($request, 'Circle not found.', 'ADMIN_CIRCLE_NOT_FOUND', 404);
        }
        $note = $service->addNote('circle', $circleId, (string) $request->validated('note'), AdminOperationContext::admin($request), AdminOperationContext::session($request), $request);

        return AdminApiResponse::success($request, ['id' => $note->id, 'note' => $note->note, 'created_at' => $note->created_at?->toIso8601String()], 201, 'Internal note added.');
    }
}
