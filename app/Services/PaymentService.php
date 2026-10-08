<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Payment;
use App\Models\User;
use App\Payments\GatewayResult;
use App\Payments\PaymentManager;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** يربط بوابات الدفع بالحملات: بدء الدفع، تأكيده، والتسوية الدورية للدفعات المعلّقة. */
class PaymentService
{
    public function __construct(
        private readonly PaymentManager $gateways,
        private readonly CampaignService $campaigns,
    ) {}

    /** @return array{payment: Payment, redirect: string} */
    public function start(Campaign $campaign, User $developer, string $gateway): array
    {
        if ($campaign->status !== Campaign::UNPAID) {
            throw new RuleViolation('هذه الحملة مدفوعة مسبقاً.');
        }

        $payment = $campaign->payments()->create([
            'user_id' => $developer->id,
            'gateway' => $gateway,
            'amount' => $campaign->price,
            'status' => Payment::PENDING,
            'external_ref' => (string) Str::uuid(),
        ]);

        $redirect = $this->gateways->driver($gateway)->initiate($payment, route('payments.return', $payment));

        return ['payment' => $payment, 'redirect' => $redirect];
    }

    /**
     * يسأل البوابة عن الحالة ويطبّقها. يعيد الحالة النهائية للدفعة لدينا.
     *
     * @param  array<string, mixed>  $returnQuery
     */
    public function confirm(Payment $payment, array $returnQuery = []): string
    {
        if ($payment->status === Payment::PAID) {
            return Payment::PAID;
        }

        $result = $this->gateways->driver($payment->gateway)->confirm($payment, $returnQuery);

        if ($result->isPaid()) {
            if ($result->amount !== null && $result->amount !== (int) $payment->amount) {
                Log::error('Payment amount mismatch', ['payment' => $payment->id, 'expected' => $payment->amount, 'gateway' => $result->amount]);
                $payment->update(['status' => Payment::MISMATCH]);

                return Payment::MISMATCH;
            }
            $this->campaigns->markPaid($payment, $result->raw);

            return Payment::PAID;
        }

        if ($result->status === GatewayResult::FAILED) {
            $payment->update(['status' => Payment::FAILED]);

            return Payment::FAILED;
        }

        return Payment::PENDING;
    }

    /** يعيد فحص الدفعات المعلّقة الحديثة (في حال ضاع رابط الرجوع أو الإشعار) */
    public function reconcilePending(): int
    {
        $count = 0;
        Payment::query()
            ->where('status', Payment::PENDING)
            ->whereNotNull('gateway_txn_id')
            ->where('created_at', '>', now()->subDays(2))
            ->each(function (Payment $payment) use (&$count) {
                try {
                    if ($this->confirm($payment) !== Payment::PENDING) {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Payment reconcile failed', ['payment' => $payment->id, 'error' => $e->getMessage()]);
                }
            });

        return $count;
    }
}
