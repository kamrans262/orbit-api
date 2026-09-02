<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\ModerationAppeal;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Admin\Moderation\Services\ModerationAppealService;
use App\Modules\Admin\Moderation\Services\ModerationPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ReviewModerationAppealController
{
    public function __invoke(Request $r, string $appealId, ModerationAppealService $s, ModerationPresenter $p): JsonResponse
    {
        $admin = $r->user();
        $session = $r->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($r, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }
        $d = $r->validate(['outcome' => ['required', Rule::in(['upheld', 'overturned', 'modified'])], 'decision_reason' => ['required', 'string', 'min:3', 'max:500'], 'modified_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'], 'require_second_review' => ['sometimes', 'boolean']]);
        $appeal = ModerationAppeal::query()->find($appealId);
        if (! $appeal) {
            return AdminApiResponse::error($r, 'Appeal not found.', 'APPEAL_NOT_FOUND', 404);
        }
        try {
            $appeal = $s->review($appeal, $admin, $session, $d['outcome'], $d['decision_reason'], $d['modified_duration_minutes'] ?? null, (bool) ($d['require_second_review'] ?? false), $r);
        } catch (ModerationDomainException $e) {
            return AdminApiResponse::error($r, $e->getMessage(), $e->errorCode, $e->status);
        }

        return AdminApiResponse::success($r,$p->appeal($appeal));
    }
}
