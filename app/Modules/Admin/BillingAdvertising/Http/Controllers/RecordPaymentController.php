<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\BillingAdvertising\Services\PaymentRefundService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecordPaymentController
{
    public function __invoke(Request $request, PaymentRefundService $service, BillingPresenter $presenter, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'subscription_id' => ['nullable', 'uuid', 'exists:user_subscriptions,id'], 'provider' => ['required', 'string', 'max:32'], 'provider_transaction_ref' => ['nullable', 'string', 'max:190'], 'type' => ['required', 'in:charge,renewal,adjustment,chargeback'], 'amount_minor' => ['required', 'integer', 'min:0'], 'currency' => ['required', 'string', 'size:3'], 'status' => ['required', 'in:pending,succeeded,failed,chargeback'], 'failure_code' => ['nullable', 'string', 'max:80'], 'metadata' => ['nullable', 'array'], 'occurred_at' => ['nullable', 'date'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $payment = $service->recordPayment($data);
        $audit->write('payment.reconciled', $request->user(), $request->attributes->get('admin_session'), 'payment_transaction', $payment->id, reason: $data['reason'], after: ['provider' => $payment->provider, 'status' => $payment->status, 'amount_minor' => (int) $payment->amount_minor], request: $request);

        return AdminApiResponse::success($request, $presenter->transaction($payment), 201);
    }
}
