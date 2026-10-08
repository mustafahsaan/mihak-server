<?php

namespace App\Domain;

use InvalidArgumentException;

/**
 * الشروط المالية لحملة اختبار واحدة. تُثبَّت لحظة إنشاء الحملة،
 * فتغيير الإعدادات لاحقاً لا يمس الحملات القائمة.
 */
final class Terms
{
    public function __construct(
        public readonly int $price,
        public readonly int $commissionPercent,
        public readonly int $share,
        public readonly int $slots,
        public readonly int $required,
        public readonly int $days,
        public readonly int $missAllowed,
    ) {
        if ($slots < 1 || $required < 1 || $required > $slots) {
            throw new InvalidArgumentException('عدد المقاعد غير صالح.');
        }
        if ($days < 1) {
            throw new InvalidArgumentException('مدة الاختبار غير صالحة.');
        }
        if ($share < 1 || $share * $slots > $price) {
            throw new InvalidArgumentException('حصة المختبِر غير صالحة.');
        }
    }

    /**
     * @param  array<string, mixed>  $s  مفاتيح الإعدادات كما في config/mihak.php
     */
    public static function fromSettings(array $s): self
    {
        $price = (int) $s['price'];
        $commission = (int) $s['commission_percent'];
        $required = (int) $s['required_testers'];
        $slots = $required + (int) $s['reserve_testers'];
        $days = (int) $s['test_days'];

        if ($price < 1 || $commission < 0 || $commission > 90 || $slots < 1) {
            throw new InvalidArgumentException('إعدادات التسعير غير صالحة.');
        }

        $commissionAmount = intdiv($price * $commission + 50, 100); // تقريب لأقرب دينار
        $share = intdiv($price - $commissionAmount, $slots);

        return new self($price, $commission, $share, $slots, $required, $days, (int) $s['miss_allowed']);
    }

    /** المبلغ المحجوز في الضمان لكل الحملة */
    public function escrow(): int
    {
        return $this->share * $this->slots;
    }

    /** حصة المنصة: العمولة مع كسور القسمة */
    public function platformCut(): int
    {
        return $this->price - $this->escrow();
    }

    public function daily(): int
    {
        return intdiv($this->share, $this->days);
    }

    /** اليوم الأخير يأخذ باقي القسمة حتى يساوي مجموع الأيام الحصة بالضبط */
    public function lastDay(): int
    {
        return $this->share - $this->daily() * ($this->days - 1);
    }

    public function amountForDay(int $day): int
    {
        if ($day < 1 || $day > $this->days) {
            throw new InvalidArgumentException("اليوم {$day} خارج مدة الاختبار.");
        }

        return $day === $this->days ? $this->lastDay() : $this->daily();
    }

    public function testsToReach(int $threshold): int
    {
        return (int) ceil($threshold / $this->share);
    }

    /** @return array<string, int> */
    public function toColumns(): array
    {
        return [
            'price' => $this->price,
            'commission_percent' => $this->commissionPercent,
            'share' => $this->share,
            'slots' => $this->slots,
            'required' => $this->required,
            'days' => $this->days,
            'miss_allowed' => $this->missAllowed,
        ];
    }
}
