<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;

/** تنفيذ استردادات المطوّرين: عبر البوابة نفسها التي دفع بها، أو يدوياً. */
class RefundService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PaymentManager $gateways,
    ) {}

    public function viaGateway(Refund $refund, User $admin): Refund
    {
        $refund = $this->pending($refund);
        $payment = $refund->payment;
        if (! $payment || ! $payment->gateway_txn_id) {
            throw new RuleViolation('لا توجد دفعة أصلية للاسترداد عبر البوابة. نفّذه يدوياً.');
        }

        $result = $this->gateways->driver($payment->gateway)->refund($payment, $refund->amount, 'Mihak refund #'.$refund->id);

        if ($result->isPaid()) {
            return $this->markPaid($refund, $admin, $result->reference);
        }
        if ($result->isFailed()) {
            // يبقى الطلب معلّقاً والمبلغ محجوزاً للمطوّر، فيمكن إعادة المحاولة أو التنفيذ يدوياً
            $refund->update(['admin_id' => $admin->id, 'admin_note' => 'رفضت البوابة الاسترداد في '.now()->format('Y-m-d H:i')]);
            throw new RuleViolation('رفضت البوابة الاسترداد. حاول لاحقاً أو نفّذه يدوياً.');
        }

        $refund->update(['gateway_ref' => $result->reference, 'admin_id' => $admin->id, 'admin_note' => 'قيد المعالجة لدى البوابة']);

        return $refund;
    }

    public function markPaid(Refund $refund, User $admin, ?string $reference): Refund
    {
        return DB::transaction(function () use ($refund, $admin, $reference) {
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status === Refund::PAID) {
                throw new RuleViolation('نُفّذ هذا الاسترداد مسبقاً.');
            }
            $refund->update(['status' => Refund::PAID, 'gateway_ref' => $reference, 'admin_id' => $admin->id, 'processed_at' => now()]);
            $this->ledger->move('refund_paid', "استرداد للمطوّر {$refund->user->name}", LedgerService::REFUND_HOLD, $refund->user, LedgerService::EXTERNAL, null, $refund->amount, $refund);

            return $refund;
        });
    }

    private function pending(Refund $refund): Refund
    {
        $fresh = Refund::query()->findOrFail($refund->id);
        if ($fresh->status === Refund::PAID) {
            throw new RuleViolation('نُفّذ هذا الاسترداد مسبقاً.');
        }

        return $fresh;
    }
}
