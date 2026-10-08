<?php

namespace App\Domain;

final class Settlement
{
    /**
     * @param  array<int, int>  $payouts  رقم المقعد => المبلغ المضاف لمحفظة المختبِر
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $completers,
        public readonly array $payouts,
        public readonly int $escrowRemaining,
    ) {}

    public function totalPaid(): int
    {
        return array_sum($this->payouts);
    }
}
