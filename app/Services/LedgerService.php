<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * دفتر قيود مزدوج. كل حركة مالية مجموعة أطراف مجموعها صفر:
 * المال لا يُخلق ولا يختفي، ينتقل فقط بين الحسابات.
 *
 * الحسابات (القيمة الموجبة = مال محفوظ فيه):
 * - external         العالم الخارجي: سالب عند استلام دفعة، موجب عند تحويل مال للخارج
 * - revenue          أرباح المنصة
 * - escrow:campaign  الضمان المحجوز لكل حملة
 * - fund             صندوق الضمان (حصص المنسحبين وأيام الغياب)
 * - wallet:user      رصيد المختبِر الافتراضي
 * - withdrawal_hold:user  مبالغ سحب قيد المراجعة أو التحويل
 * - refund_hold:user      مبالغ استرداد للمطوّر قيد التنفيذ
 */
class LedgerService
{
    public const EXTERNAL = 'external';

    public const REVENUE = 'revenue';

    public const ESCROW = 'escrow';

    public const FUND = 'fund';

    public const WALLET = 'wallet';

    public const WITHDRAWAL_HOLD = 'withdrawal_hold';

    public const REFUND_HOLD = 'refund_hold';

    public const LABELS = [
        self::EXTERNAL => 'خارج المنصة',
        self::REVENUE => 'أرباح المنصة',
        self::ESCROW => 'الضمان',
        self::FUND => 'صندوق الضمان',
        self::WALLET => 'أرصدة المختبِرين',
        self::WITHDRAWAL_HOLD => 'سحوبات قيد المراجعة',
        self::REFUND_HOLD => 'استردادات قيد التنفيذ',
    ];

    /**
     * @param  array<int, array{0:string, 1:?Model, 2:int}>  $legs  [الحساب، صاحب الحساب، المبلغ بإشارته]
     */
    public function post(string $type, string $description, array $legs, ?Model $reference = null): LedgerTransaction
    {
        $legs = array_values(array_filter($legs, fn (array $leg) => (int) $leg[2] !== 0));
        $sum = array_sum(array_map(fn (array $leg) => (int) $leg[2], $legs));
        if ($sum !== 0) {
            throw new LogicException("حركة غير متوازنة ({$type}): الفرق {$sum}");
        }
        if ($legs === []) {
            throw new LogicException("حركة فارغة ({$type})");
        }

        return DB::transaction(function () use ($type, $description, $legs, $reference) {
            $transaction = LedgerTransaction::query()->create([
                'type' => $type,
                'description' => mb_substr($description, 0, 255),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);

            foreach ($legs as [$account, $holder, $amount]) {
                $transaction->entries()->create([
                    'account' => $account,
                    'holder_type' => $holder?->getMorphClass(),
                    'holder_id' => $holder?->getKey(),
                    'amount' => (int) $amount,
                ]);
            }

            return $transaction;
        });
    }

    /** نقل مبلغ موجب من حساب إلى آخر */
    public function move(string $type, string $description, string $fromAccount, ?Model $fromHolder, string $toAccount, ?Model $toHolder, int $amount, ?Model $reference = null): ?LedgerTransaction
    {
        if ($amount === 0) {
            return null;
        }
        if ($amount < 0) {
            throw new LogicException('المبلغ يجب أن يكون موجباً.');
        }

        return $this->post($type, $description, [
            [$fromAccount, $fromHolder, -$amount],
            [$toAccount, $toHolder, $amount],
        ], $reference);
    }

    public function balance(string $account, ?Model $holder = null): int
    {
        $query = LedgerEntry::query()->where('account', $account);
        if ($holder !== null) {
            $query->where('holder_type', $holder->getMorphClass())->where('holder_id', $holder->getKey());
        }

        return (int) $query->sum('amount');
    }

    /** @return array<string, int> مجموع كل حساب على مستوى المنصة */
    public function totals(): array
    {
        $totals = array_fill_keys(array_keys(self::LABELS), 0);
        foreach (LedgerEntry::query()->selectRaw('account, SUM(amount) as total')->groupBy('account')->get() as $row) {
            $totals[$row->account] = (int) $row->total;
        }

        return $totals;
    }
}
