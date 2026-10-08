<?php

namespace App\Domain;

/**
 * قواعد المنصة كدوال خالصة بلا قاعدة بيانات، حتى تُختبر بسهولة.
 * الخدمات في app/Services تطبّق قراراتها وتسجّل الحركات المالية.
 */
final class CampaignRules
{
    public const ACCRUE = 'accrue';

    public const MISS = 'miss';

    public const REMOVE = 'remove';

    public const REASON_UNINSTALLED = 'uninstalled';

    public const REASON_TOO_MANY_MISSES = 'too_many_misses';

    public const REASON_LEFT = 'left';

    public const REASON_CAMPAIGN_FAILED = 'campaign_failed';

    /**
     * يقرر مصير كل مقعد نشط عند إغلاق يوم من أيام الاختبار.
     *
     * - آخر تقرير يقول إن التطبيق غير مثبّت: خروج فوري.
     * - مدة الاستخدام في اليوم >= الحد الأدنى: يُضاف مبلغ اليوم إلى المعلّق.
     * - غير ذلك: غياب، وتجاوز الغياب المسموح يعني الخروج.
     *
     * @param  array<int, array{accrued:int, misses:int, report:?array{installed:bool, foreground_seconds:int}}>  $seats
     * @return array<int, array{action:string, amount:int, misses:int, reason:?string}>
     */
    public static function closeDay(Terms $terms, int $dayNumber, array $seats, int $minOpenSeconds): array
    {
        $decisions = [];

        foreach ($seats as $id => $seat) {
            $report = $seat['report'] ?? null;

            if ($report !== null && $report['installed'] === false) {
                $decisions[$id] = ['action' => self::REMOVE, 'amount' => 0, 'misses' => $seat['misses'], 'reason' => self::REASON_UNINSTALLED];

                continue;
            }

            if ($report !== null && $report['foreground_seconds'] >= $minOpenSeconds) {
                $decisions[$id] = ['action' => self::ACCRUE, 'amount' => $terms->amountForDay($dayNumber), 'misses' => $seat['misses'], 'reason' => null];

                continue;
            }

            $misses = $seat['misses'] + 1;
            $decisions[$id] = $misses > $terms->missAllowed
                ? ['action' => self::REMOVE, 'amount' => 0, 'misses' => $misses, 'reason' => self::REASON_TOO_MANY_MISSES]
                : ['action' => self::MISS, 'amount' => 0, 'misses' => $misses, 'reason' => null];
        }

        return $decisions;
    }

    /**
     * تسوية الحملة في نهاية المدة.
     *
     * نجاح (عدد المكملين >= المطلوب): كل مكمل يأخذ ما اكتسبه، والباقي يذهب لصندوق الضمان.
     * فشل: لا يُدفع لأحد (إلا إذا فُعّل خيار الدفع للمكملين)، ويبقى المبلغ في الضمان
     * حتى يختار المطوّر الاسترداد أو إعادة الاختبار.
     *
     * @param  array<int, int>  $completers  رقم المقعد => المبلغ المكتسب
     */
    public static function settle(Terms $terms, array $completers, bool $failurePaysCompleters): Settlement
    {
        $success = count($completers) >= $terms->required;
        $payouts = ($success || $failurePaysCompleters) ? $completers : [];

        return new Settlement(
            success: $success,
            completers: count($completers),
            payouts: $payouts,
            escrowRemaining: $terms->escrow() - array_sum($payouts),
        );
    }

    /** المبلغ الذي يعود للمطوّر عند اختيار الاسترداد */
    public static function refundAmount(Terms $terms, int $escrowRemaining, bool $includePlatformCut): int
    {
        return $escrowRemaining + ($includePlatformCut ? $terms->platformCut() : 0);
    }

    /**
     * تمويل إعادة الاختبار: من ضمان الحملة الفاشلة أولاً، ثم صندوق الضمان، ثم أرباح المنصة.
     *
     * @return array{need:int, from_escrow:int, from_fund:int, from_revenue:int, escrow_leftover:int}
     */
    public static function rerunFunding(Terms $terms, int $escrowRemaining, int $fundBalance): array
    {
        $need = $terms->escrow();
        $fromEscrow = min($escrowRemaining, $need);
        $deficit = $need - $fromEscrow;
        $fromFund = min(max($fundBalance, 0), $deficit);

        return [
            'need' => $need,
            'from_escrow' => $fromEscrow,
            'from_fund' => $fromFund,
            'from_revenue' => $deficit - $fromFund,
            'escrow_leftover' => $escrowRemaining - $fromEscrow,
        ];
    }

    /** يعيد سبب الرفض أو null إذا كان السحب مسموحاً */
    public static function withdrawalError(int $balance, int $amount, int $minimum): ?string
    {
        if ($amount < max(1, $minimum)) {
            return 'below_minimum';
        }
        if ($amount > $balance) {
            return 'insufficient_balance';
        }

        return null;
    }
}
