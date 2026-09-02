<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Modules\Admin\Moderation\Services\ModerationDirectoryService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListModerationReportsController
{
    public function __invoke(Request $r, ModerationDirectoryService $s): JsonResponse
    {
        return AdminApiResponse::success($r, $s->reports($r->query()));
    }
}
