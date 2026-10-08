<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class Dates
{
    public static function tz(): string
    {
        return config('mihak.timezone', 'Asia/Baghdad');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::tz());
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /**
     * آخر تاريخ أُغلق أو يمكن إغلاقه: يوم أمس، لكن فقط بعد ساعة الإغلاق (03:00).
     * قبل ذلك يبقى أمس مفتوحاً لاستقبال تقارير آخر الليل.
     */
    public static function lastClosableDate(): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', config('mihak.close_day_at', '03:00')));
        $now = self::now();
        $cutoff = $now->startOfDay()->setTime($h, $m);

        return $now->lt($cutoff) ? $now->subDays(2)->startOfDay() : $now->subDay()->startOfDay();
    }

    private const MONTHS = ['كانون الثاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران', 'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين الثاني', 'كانون الأول'];

    /** مثال: 8 تشرين الأول */
    public static function arabic(?\DateTimeInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        return (int) $date->format('j').' '.self::MONTHS[(int) $date->format('n') - 1];
    }

    public static function money(int|float|null $amount): string
    {
        return number_format((int) $amount).' د.ع';
    }
}
