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

final class CompleteProviderRefundController
{
    public function __invoke(Request $request, string $refundId, PaymentRefundService $service, BillingPresenter $presenter, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['succeeded' => ['required', 'boolean'], 'provider_result' => ['required', 'string', 'max:500'], 'provider_ref' => ['nullable', 'string', 'max:190'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $refund = PaymentRefund::query()->find($refundId);
        if ($refund === null) {
            return AdminApiResponse::error($request, 'Refund not found.', 'REFUND_NOT_FOUND', 404);
        }try {
            $refund = $service->completeProvider($refund, $data['provider_result'], $data['provider_ref'] ?? null, $request->user(), filter_var($data['succeeded'], FILTER_VALIDATE_BOOL));
        } catch (BillingAdvertisingDomainException $e) {
            return AdminApiResponse::error($request, $e->getMessage(), $e->errorCode, $e->status);
        } $audit->write('refund.provider_result_recorded', $request->user(), $request->attributes->get('admin_session'), 'payment_refund', $refund->id, reason: $data['reason'], after: ['status' => $refund->status], request: $request);

        return AdminApiResponse::success($request,$presenter->refund($refund));
    }
}
