<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingPlan;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListBillingPlansController
{
    public function __invoke(Request $request): JsonResponse
    {
        $items = BillingPlan::query()->orderBy('rank')->get()->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name, 'description' => $p->description, 'status' => $p->status, 'rank' => (int) $p->rank]);

        return AdminApiResponse::success($request, $items);
    }
}
