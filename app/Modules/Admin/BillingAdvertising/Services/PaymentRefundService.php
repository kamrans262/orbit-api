<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Services;

use App\Models\AdminUser;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Modules\Admin\BillingAdvertising\Exceptions\BillingAdvertisingDomainException;
use App\Modules\Admin\PrivacySupport\Services\ContactHistoryService;

final class PaymentRefundService
{
    public function __construct(private readonly ContactHistoryService $contacts) {}

    public function recordPayment(array $data): PaymentTransaction
    {
        $metadata = $this->sanitize($data['metadata'] ?? []);
        $attributes = [
            'user_id' => $data['user_id'], 'subscription_id' => $data['subscription_id'] ?? null,
            'provider' => $data['provider'], 'provider_transaction_ref' => $data['provider_transaction_ref'] ?? null,
            'type' => $data['type'], 'amount_minor' => $data['amount_minor'], 'currency' => strtoupper($data['currency']),
            'status' => $data['status'], 'failure_code' => $data['failure_code'] ?? null,
            'metadata' => $metadata, 'occurred_at' => $data['occurred_at'] ?? now(),
        ];

        if (($data['provider_transaction_ref'] ?? null) === null) {
            return PaymentTransaction::query()->create($attributes);
        }

        return PaymentTransaction::query()->updateOrCreate(
            ['provider' => $data['provider'], 'provider_transaction_ref' => $data['provider_transaction_ref']],
            $attributes,
        );
    }

    public function requestRefund(PaymentTransaction $payment, int $amount, string $reason, ?string $note, AdminUser $admin): PaymentRefund
    {
        if (! in_array($payment->status, ['succeeded', 'partially_refunded'], true)) {
            throw new BillingAdvertisingDomainException('PAYMENT_NOT_REFUNDABLE', 409, 'Only successful payments can be refunded.');
        }
        $already = (int) PaymentRefund::query()->where('payment_transaction_id', $payment->id)->whereIn('status', ['requested', 'approved', 'pending_provider', 'succeeded'])->sum('amount_minor');
        if ($amount < 1 || $amount > ((int) $payment->amount_minor - $already)) {
            throw new BillingAdvertisingDomainException('REFUND_AMOUNT_INVALID', 422, 'Refund amount exceeds the remaining refundable amount.');
        }

        return PaymentRefund::query()->create([
            'payment_transaction_id' => $payment->id, 'user_id' => $payment->user_id,
            'amount_minor' => $amount, 'currency' => $payment->currency, 'status' => 'requested',
            'reason' => mb_substr($reason, 0, 500), 'internal_note' => $note,
            'requested_by_admin_id' => $admin->id, 'requested_at' => now(),
        ]);
    }

    public function decide(PaymentRefund $refund, string $decision, AdminUser $admin, ?string $note = null): PaymentRefund
    {
        if ($refund->status !== 'requested') {
            throw new BillingAdvertisingDomainException('REFUND_ALREADY_DECIDED', 409, 'Refund has already been decided.');
        }
        if ($decision === 'reject') {
            $refund->forceFill(['status' => 'rejected', 'decided_by_admin_id' => $admin->id, 'decided_at' => now(), 'internal_note' => $note ?? $refund->internal_note])->save();

            return $refund->refresh();
        }
        $payment = PaymentTransaction::query()->findOrFail($refund->payment_transaction_id);
        $status = $payment->provider === 'manual' ? 'succeeded' : 'pending_provider';
        $refund->forceFill([
            'status' => $status, 'decided_by_admin_id' => $admin->id, 'decided_at' => now(),
            'completed_at' => $status === 'succeeded' ? now() : null,
            'provider_result' => $status === 'succeeded' ? 'manual_ledger_refund' : 'provider_confirmation_required',
            'internal_note' => $note ?? $refund->internal_note,
        ])->save();
        if ($status === 'succeeded') {
            $this->refreshPaymentRefundStatus($payment);
            $this->contacts->record((int) $refund->user_id, 'payment.refund.completed', 'system', 'outbound', 'Refund completed', 'An Orbit payment refund was completed.', 'payment_refund', $refund->id, $admin, ['amount_minor' => (int) $refund->amount_minor, 'currency' => $refund->currency]);
        }

        return $refund->refresh();
    }

    public function completeProvider(PaymentRefund $refund, string $result, ?string $providerRef, AdminUser $admin, bool $succeeded): PaymentRefund
    {
        if ($refund->status !== 'pending_provider') {
            throw new BillingAdvertisingDomainException('REFUND_NOT_PENDING_PROVIDER', 409, 'Refund is not awaiting provider confirmation.');
        }
        $refund->forceFill([
            'status' => $succeeded ? 'succeeded' : 'failed', 'provider_result' => mb_substr($result, 0, 500),
            'provider_ref' => $providerRef, 'completed_at' => now(), 'decided_by_admin_id' => $admin->id,
        ])->save();
        if ($succeeded) {
            $payment = PaymentTransaction::query()->findOrFail($refund->payment_transaction_id);
            $this->refreshPaymentRefundStatus($payment);
            $this->contacts->record((int) $refund->user_id, 'payment.refund.completed', 'system', 'outbound', 'Refund completed', 'An Orbit payment refund was completed.', 'payment_refund', $refund->id, $admin, ['amount_minor' => (int) $refund->amount_minor, 'currency' => $refund->currency]);
        }

        return $refund->refresh();
    }

    private function refreshPaymentRefundStatus(PaymentTransaction $payment): void
    {
        $refunded = (int) PaymentRefund::query()->where('payment_transaction_id', $payment->id)->where('status', 'succeeded')->sum('amount_minor');
        $payment->forceFill(['status' => $refunded >= (int) $payment->amount_minor ? 'refunded' : 'partially_refunded'])->save();
    }

    private function sanitize(array $metadata): array
    {
        $blocked = ['card', 'pan', 'cvv', 'cvc', 'secret', 'token', 'password', 'authorization', 'private_key'];
        $out = [];
        foreach ($metadata as $key => $value) {
            $lower = strtolower((string) $key);
            if (collect($blocked)->contains(fn (string $fragment): bool => str_contains($lower, $fragment))) {
                continue;
            }
            $out[$key] = is_array($value) ? $this->sanitize($value) : (is_string($value) ? mb_substr($value, 0, 500) : $value);
        }

        return $out;
    }
}
