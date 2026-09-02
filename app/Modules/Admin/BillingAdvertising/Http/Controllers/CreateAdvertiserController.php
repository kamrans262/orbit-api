<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\Advertiser;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateAdvertiserController
{
    public function __invoke(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'external_ref' => ['nullable', 'string', 'max:120'], 'contact_email' => ['nullable', 'email', 'max:190']]);
        $a = Advertiser::query()->create([...$data, 'status' => 'active']);
        $audit->write('advertising.advertiser.created', $request->user(), $request->attributes->get('admin_session'), 'advertiser', $a->id, after: ['name' => $a->name, 'status' => $a->status], request: $request);

        return AdminApiResponse::success($request, ['id' => $a->id], 201);
    }
}
