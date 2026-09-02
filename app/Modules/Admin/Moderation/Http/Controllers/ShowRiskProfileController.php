<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Moderation\Services\ModerationDirectoryService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowRiskProfileController
{
    public function __invoke(Request $r, string $userId, ModerationDirectoryService $s): JsonResponse
    {
        $u = ctype_digit($userId) ? User::query()->find((int) $userId) : null;

        return $u ? AdminApiResponse::success($r, $s->riskUser($u)) : AdminApiResponse::error($r, 'Risk profile user not found.', 'RISK_USER_NOT_FOUND', 404);
    }
}
