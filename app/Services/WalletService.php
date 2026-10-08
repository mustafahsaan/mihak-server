<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Withdrawal;

class WalletService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly LedgerService $ledger,
    ) {}

    /** @return array{balance:int, pending:int, on_hold:int, withdraw_min:int, can_withdraw:bool, remaining_to_min:int} */
    public function summary(User $tester): array
    {
        $balance = $this->ledger->balance(LedgerService::WALLET, $tester);
        $min = $this->settings->int('withdraw_min');
        $pending = (int) Enrollment::query()
            ->where('tester_id', $tester->id)
            ->where('status', Enrollment::ACTIVE)
            ->whereHas('campaign', fn ($q) => $q->where('status', Campaign::TESTING))
            ->sum('accrued');

        return [
            'balance' => $balance,
            'pending' => $pending,
            'on_hold' => $this->ledger->balance(LedgerService::WITHDRAWAL_HOLD, $tester),
            'withdraw_min' => $min,
            'can_withdraw' => $balance >= $min && $balance > 0,
            'remaining_to_min' => max(0, $min - $balance),
        ];
    }

    /**
     * سجل المختبِر: الاختبارات المنتهية والسحوبات، الأحدث أولاً.
     *
     * @return \Illuminate\Support\Collection<int, array{type:string, title:string, amount:int, date:?string}>
     */
    public function history(User $tester): \Illuminate\Support\Collection
    {
        $items = collect();

        $tester->enrollments()
            ->whereIn('status', [Enrollment::COMPLETED, Enrollment::REMOVED, Enrollment::VOIDED])
            ->with('campaign.androidApp')
            ->latest('ended_at')
            ->limit(50)
            ->get()
            ->each(function (Enrollment $e) use ($items) {
                $app = $e->campaign->androidApp->name;
                $items->push([
                    'type' => $e->status,
                    'title' => $e->status === Enrollment::COMPLETED ? "أكملت «{$app}»" : "خرجت من «{$app}»: ".$e->reasonLabel(),
                    'amount' => $e->status === Enrollment::COMPLETED ? (int) $e->accrued : -(int) $e->accrued,
                    'date' => $e->ended_at?->toDateString(),
                ]);
            });

        $tester->withdrawals()->latest('id')->limit(30)->get()->each(function (Withdrawal $w) use ($items) {
            $items->push([
                'type' => 'withdrawal_'.$w->status,
                'title' => 'سحب إلى '.$w->methodLabel().' · '.$w->label().($w->admin_note ? ' ('.$w->admin_note.')' : ''),
                'amount' => -$w->amount,
                'date' => $w->created_at?->toDateString(),
            ]);
        });

        return $items->sortByDesc('date')->values();
    }

    /**
     * فحص سلامة الحسابات: يقارن الدفتر بجداول الدفعات والسحوبات والاستردادات.
     *
     * @return array{ok:bool, checks:array<int, array{label:string, ok:bool, detail:string}>, totals:array<string,int>}
     */
    public function integrity(): array
    {
        $totals = $this->ledger->totals();
        $received = (int) Payment::query()->where('status', Payment::PAID)->sum('amount');
        $paidOut = (int) Withdrawal::query()->where('status', Withdrawal::PAID)->sum('amount')
            + (int) Refund::query()->where('status', Refund::PAID)->sum('amount');
        $held = $received - $paidOut;
        $internal = array_sum($totals) - $totals[LedgerService::EXTERNAL];

        $negativeWallets = \App\Models\LedgerEntry::query()
            ->whereIn('account', [LedgerService::WALLET, LedgerService::ESCROW])
            ->selectRaw('account, holder_type, holder_id, SUM(amount) as total')
            ->groupBy('account', 'holder_type', 'holder_id')
            ->havingRaw('SUM(amount) < 0')
            ->get()
            ->count(); // count() مباشرة على استعلام مجمّع يعيد عدد أول مجموعة فقط

        $checks = [
            ['label' => 'مجموع كل الحسابات صفر', 'ok' => array_sum($totals) === 0, 'detail' => number_format(array_sum($totals))],
            ['label' => 'المدفوعات ناقص المحوَّل = المال المحفوظ', 'ok' => $held === $internal, 'detail' => number_format($received).' − '.number_format($paidOut).' = '.number_format($held).' مقابل '.number_format($internal)],
            ['label' => 'لا رصيد سالب في المحافظ والضمان', 'ok' => $negativeWallets === 0, 'detail' => $negativeWallets.' حساب سالب'],
        ];

        return [
            'ok' => collect($checks)->every(fn ($c) => $c['ok']),
            'checks' => $checks,
            'totals' => $totals + ['received' => $received, 'paid_out' => $paidOut],
        ];
    }
}
