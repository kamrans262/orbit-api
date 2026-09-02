<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\PaymentRefund;
use App\Modules\Admin\BillingAdvertising\Exceptions\BillingAdvertisingDomainException;
use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\BillingAdvertising\Services\PaymentRefundService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DecideRefundController
{
    public function __invoke(Request $request, string $refundId, PaymentRefundService $service, BillingPresenter $presenter, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason' => ['required', 'string', 'min:3', 'max:500'], 'internal_note' => ['nullable', 'string', 'max:2000']]);
        $refund = PaymentRefund::query()->find($refundId);
        if ($refund === null) {
            return AdminApiResponse::error($request, 'Refund not found.', 'REFUND_NOT_FOUND', 404);
        }try {
            $refund = $service->decide($refund, $data['decision'], $request->user(), $data['internal_note'] ?? null);
        } catch (BillingAdvertisingDomainException $e) {
            return AdminApiResponse::error($request, $e->getMessage(), $e->errorCode, $e->status);
        } $audit->write('refund.decided', $request->user(), $request->attributes->get('admin_session'), 'payment_refund', $refund->id, reason: $data['reason'], after: ['status' => $refund->status, 'decision' => $data['decision']], request: $request);

        return AdminApiResponse::success($request, $presenter->refund($refund), $refund->status === 'pending_provider' ? 202 : 200);
    }
}
