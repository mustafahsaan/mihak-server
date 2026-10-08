<?php

namespace App\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * بوابة تجريبية للتشغيل المحلي (MIHAK_FAKE_PAYMENTS=true).
 * تعرض صفحة فيها «ادفع» و«إلغاء» بدل صفحة زين كاش أو كي كارد. لا تُفعّلها على السيرفر الحقيقي.
 */
class FakeGateway implements PaymentGateway
{
    public function initiate(Payment $payment, string $returnUrl): string
    {
        $payment->gateway_txn_id = 'FAKE-'.$payment->id;
        $payment->remember(['fake_return_url' => $returnUrl, 'fake_status' => 'pending']);

        return route('fake-gateway.show', $payment);
    }

    public function confirm(Payment $payment, array $returnQuery = []): GatewayResult
    {
        return new GatewayResult(
            status: match ($payment->payload['fake_status'] ?? 'pending') {
                'paid' => GatewayResult::PAID,
                'failed' => GatewayResult::FAILED,
                default => GatewayResult::PENDING,
            },
            amount: $payment->amount,
            reference: $payment->gateway_txn_id,
        );
    }

    public function refund(Payment $payment, int $amount, string $reason): GatewayResult
    {
        return new GatewayResult(GatewayResult::PAID, $amount, 'FAKE-REFUND-'.$payment->id);
    }

    public function parseWebhook(Request $request): array
    {
        return ['external_ref' => $request->input('external_ref'), 'gateway_txn_id' => $request->input('gateway_txn_id')];
    }
}
