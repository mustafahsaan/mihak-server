<?php

namespace App\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** يبدأ عملية الدفع ويعيد رابط صفحة الدفع التي يُحوَّل إليها المطوّر */
    public function initiate(Payment $payment, string $returnUrl): string;

    /**
     * يسأل البوابة مباشرة عن حالة الدفعة. لا نثق بما يأتي في رابط الرجوع وحده.
     *
     * @param  array<string, mixed>  $returnQuery  معاملات رابط الرجوع إن وجدت
     */
    public function confirm(Payment $payment, array $returnQuery = []): GatewayResult;

    /** يعيد مبلغاً (كاملاً أو جزئياً) إلى الدافع */
    public function refund(Payment $payment, int $amount, string $reason): GatewayResult;

    /**
     * يتحقق من إشعار البوابة (webhook) ويعيد مرجع الدفعة لدينا أو لدى البوابة.
     *
     * @return array{external_ref:?string, gateway_txn_id:?string}
     */
    public function parseWebhook(Request $request): array;
}
