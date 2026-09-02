<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Modules\Admin\BillingAdvertising\Exceptions\BillingAdvertisingDomainException;
use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\BillingAdvertising\Services\PaymentRefundService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateRefundController
{
    public function __invoke(Request $request, string $paymentId, PaymentRefundService $service, BillingPresenter $presenter, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['amount_minor' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500'], 'internal_note' => ['nullable', 'string', 'max:2000']]);
        $payment = PaymentTransaction::query()->find($paymentId);
        if ($payment === null) {
            return AdminApiResponse::error($request, 'Payment not found.', 'PAYMENT_NOT_FOUND', 404);
        }try {
            $refund = $service->requestRefund($payment, (int) $data['amount_minor'], $data['reason'], $data['internal_note'] ?? null, $request->user());
        } catch (BillingAdvertisingDomainException $e) {
            return AdminApiResponse::error($request, $e->getMessage(), $e->errorCode, $e->status);
        } $audit->write('refund.requested', $request->user(), $request->attributes->get('admin_session'), 'payment_refund', $refund->id, reason: $data['reason'], after: ['payment_id' => $payment->id, 'amount_minor' => (int) $refund->amount_minor], request: $request);

        return AdminApiResponse::success($request,$presenter->refund($refund),201);
    }
}
