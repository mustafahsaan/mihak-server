<?php

namespace App\Console\Commands;

use App\Services\DayCloseService;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CloseDayCommand extends Command
{
    protected $signature = 'mihak:close-day {--date= : إغلاق كل الأيام حتى هذا التاريخ (Y-m-d)}';

    protected $description = 'يغلق أيام الاختبار المنتهية: يحسب المبالغ والغياب ويسوّي الحملات المكتملة';

    public function handle(DayCloseService $service): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), Dates::tz())->startOfDay()
            : Dates::lastClosableDate();

        $closed = $service->closeThrough($date);
        $this->info('أُغلق '.count($closed).' يوماً حتى '.$date->toDateString());
        foreach ($closed as $row) {
            $this->line("  الحملة #{$row['campaign']}: اليوم {$row['day']}");
        }

        return self::SUCCESS;
    }
}
