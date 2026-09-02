<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\PrivacySupport\Services\ContactHistoryService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdminUserContactHistoryController
{
    public function __invoke(Request $request, string $userId, ContactHistoryService $contacts): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return AdminApiResponse::error($request, 'User not found.', 'USER_NOT_FOUND', 404);
        }

        $data = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $page = $contacts->paginateForUser((int) $user->id, (int) ($data['per_page'] ?? 25));

        return AdminApiResponse::success($request, [
            'items' => collect($page->items())->map(fn ($event): array => [
                'id' => $event->id,
                'channel' => $event->channel,
                'kind' => $event->kind,
                'direction' => $event->direction,
                'subject' => $event->subject,
                'summary' => $event->summary,
                'source_type' => $event->source_type,
                'source_id' => $event->source_id,
                'actor_admin_id' => $event->actor_admin_id,
                'metadata' => $event->metadata ?? [],
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ])->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }
}
