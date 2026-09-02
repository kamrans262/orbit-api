<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\ModerationAppeal;
use App\Modules\Admin\Moderation\Services\ModerationPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowModerationAppealController
{
    public function __invoke(Request $r, string $appealId, ModerationPresenter $p): JsonResponse
    {
        $a = ModerationAppeal::query()->find($appealId);

        return $a ? AdminApiResponse::success($r, $p->appeal($a)) : AdminApiResponse::error($r, 'Appeal not found.', 'APPEAL_NOT_FOUND', 404);
    }
}
