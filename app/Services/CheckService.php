<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\DailyCheck;
use App\Models\Enrollment;
use App\Support\Dates;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * يستقبل تقارير الهاتف: هل التطبيق مثبّت، وكم ثانية استُخدم اليوم.
 * يُحفظ لكل يوم أعلى مدة استخدام وصلت، وآخر حالة تثبيت.
 */
class CheckService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function record(Enrollment $enrollment, CarbonImmutable $date, bool $installed, int $foregroundSeconds): DailyCheck
    {
        $date = $date->startOfDay();
        $today = Dates::today();
        $yesterdayOpen = $today->subDay()->gt(Dates::lastClosableDate());

        if (! ($date->equalTo($today) || ($yesterdayOpen && $date->equalTo($today->subDay())))) {
            throw new RuleViolation('تاريخ التقرير خارج الفترة المقبولة.');
        }

        $campaign = $enrollment->campaign;
        if ($enrollment->status !== Enrollment::ACTIVE || $campaign->status !== Campaign::TESTING) {
            throw new RuleViolation('هذا الاختبار غير جارٍ.');
        }

        $day = $campaign->dayNumberFor($date);
        if ($day === null) {
            throw new RuleViolation('هذا التاريخ ليس من أيام الاختبار.');
        }
        if ($day <= $campaign->days_done) {
            throw new RuleViolation('أُغلق هذا اليوم.');
        }

        $seconds = max(0, min($foregroundSeconds, 86400));

        return DB::transaction(function () use ($enrollment, $day, $date, $installed, $seconds) {
            /** @var DailyCheck $check */
            $check = DailyCheck::query()->lockForUpdate()->firstOrNew([
                'enrollment_id' => $enrollment->id,
                'day_number' => $day,
            ]);
            $check->check_date = $date->toDateString();
            $check->installed = $installed;
            $check->foreground_seconds = max((int) $check->foreground_seconds, $seconds);
            $check->reports = (int) $check->reports + 1;
            $check->reported_at = now();
            $check->save();

            return $check;
        });
    }

    public function isOpened(?DailyCheck $check): bool
    {
        return $check !== null && $check->installed && $check->foreground_seconds >= $this->settings->int('min_open_seconds');
    }
}
