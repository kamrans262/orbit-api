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

final class AssignModerationAppealController
{
    public function __invoke(Request $r, string $appealId, ModerationAppealService $s, ModerationPresenter $p): JsonResponse
    {
        $admin = $r->user();
        $session = $r->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($r, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }
        $d = $r->validate(['assigned_admin_id' => ['nullable', 'integer', 'exists:admin_users,id'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $appeal = ModerationAppeal::query()->find($appealId);
        if (! $appeal) {
            return AdminApiResponse::error($r, 'Appeal not found.', 'APPEAL_NOT_FOUND', 404);
        }
        $assignee = isset($d['assigned_admin_id']) ? AdminUser::query()->find((int) $d['assigned_admin_id']) : null;
        try {
            $appeal = $s->assign($appeal, $assignee, $admin, $session, $d['reason'], $r);
        } catch (ModerationDomainException $e) {
            return AdminApiResponse::error($r, $e->getMessage(), $e->errorCode, $e->status);
        }

        return AdminApiResponse::success($r,$p->appeal($appeal));
    }
}
