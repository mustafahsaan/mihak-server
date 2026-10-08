<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Enrollment;
use App\Services\CheckService;
use App\Services\DayCloseService;
use App\Support\Dates;
use Illuminate\Console\Command;

/**
 * للتجربة المحلية فقط: يحاكي تقارير الهواتف لكل المقاعد النشطة في حملة، ثم يغلق اليوم.
 * يسمح بتجربة دورة 16 يوماً كاملة في دقائق بدون أجهزة حقيقية.
 */
class DemoChecksCommand extends Command
{
    protected $signature = 'mihak:demo-day {campaign : رقم الحملة} {--skip=* : أرقام مختبِرين لا يفتحون التطبيق هذا اليوم}';

    protected $description = '(محلي فقط) يحاكي يوم اختبار كاملاً لحملة ويغلقه';

    public function handle(CheckService $checks, DayCloseService $closer): int
    {
        if (app()->isProduction()) {
            $this->error('هذا الأمر للتجربة المحلية فقط.');

            return self::FAILURE;
        }

        /** @var Campaign $campaign */
        $campaign = Campaign::query()->findOrFail($this->argument('campaign'));
        if ($campaign->status !== Campaign::TESTING) {
            $this->error('الحملة ليست في مرحلة الاختبار (الحالة: '.$campaign->label().').');

            return self::FAILURE;
        }

        // نرجع تاريخ البدء للخلف حتى يصبح «اليوم» هو اليوم التالي المطلوب إغلاقه
        $day = $campaign->days_done + 1;
        $campaign->update(['started_on' => Dates::today()->subDays($day - 1)->toDateString()]);
        $campaign->refresh();

        $skip = array_map('intval', (array) $this->option('skip'));
        foreach ($campaign->enrollments()->where('status', Enrollment::ACTIVE)->get() as $enrollment) {
            if (! in_array($enrollment->tester_id, $skip, true)) {
                $checks->record($enrollment, Dates::today(), true, 120);
            }
        }

        $closer->closeDay($campaign, $day);
        $campaign->refresh();
        $this->info("أُغلق اليوم {$day} من {$campaign->days} · الحالة: {$campaign->label()}");

        return self::SUCCESS;
    }
}
