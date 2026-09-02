<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\Advertiser;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UpdateAdvertiserController
{
    public function __invoke(Request $request, string $advertiserId, AdminAuditLogger $audit): JsonResponse
    {
        $a = Advertiser::query()->find($advertiserId);
        if ($a === null) {
            return AdminApiResponse::error($request, 'Advertiser not found.', 'ADVERTISER_NOT_FOUND', 404);
        }$data = $request->validate(['name' => ['sometimes', 'string', 'max:120'], 'status' => ['sometimes', 'in:active,paused,disabled'], 'external_ref' => ['nullable', 'string', 'max:120'], 'contact_email' => ['nullable', 'email', 'max:190']]);
        $before = $a->only(['name', 'status', 'external_ref']);
        $a->fill($data)->save();
        $audit->write('advertising.advertiser.updated', $request->user(), $request->attributes->get('admin_session'), 'advertiser', $a->id, before: $before, after: $a->only(['name', 'status', 'external_ref']), request: $request);

        return AdminApiResponse::success($request, ['id' => $a->id, 'status' => $a->status]);
    }
}
