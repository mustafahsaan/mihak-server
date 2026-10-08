<?php

namespace App\Services;

use App\Domain\CampaignRules;
use App\Models\Campaign;
use App\Models\DailyCheck;
use App\Models\Enrollment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * يُغلق أيام الاختبار المنتهية: يضيف مبلغ اليوم لمن فتح التطبيق، ويسجل الغياب،
 * ويُخرج من ألغى التثبيت أو تجاوز الغياب. إذا فات الجدولة يوم، يُغلق الأيام المتأخرة بالترتيب.
 */
class DayCloseService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SettlementService $settlement,
    ) {}

    /** @return array<int, array{campaign:int, day:int}> */
    public function closeThrough(CarbonImmutable $date): array
    {
        $closed = [];
        $ids = Campaign::query()
            ->where('status', Campaign::TESTING)
            ->whereDate('started_on', '<=', $date->toDateString())
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            while (true) {
                /** @var Campaign $campaign */
                $campaign = Campaign::query()->findOrFail($id);
                $next = $campaign->days_done + 1;
                if ($campaign->status !== Campaign::TESTING || $next > $campaign->days) {
                    break;
                }
                if ($campaign->started_on->addDays($next - 1)->gt($date)) {
                    break;
                }
                $this->closeDay($campaign, $next);
                $closed[] = ['campaign' => $campaign->id, 'day' => $next];
            }
        }

        return $closed;
    }

    public function closeDay(Campaign $campaign, int $dayNumber): void
    {
        DB::transaction(function () use ($campaign, $dayNumber) {
            /** @var Campaign $campaign */
            $campaign = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();
            if ($campaign->status !== Campaign::TESTING || $campaign->days_done + 1 !== $dayNumber) {
                return; // أُغلق مسبقاً من عملية أخرى
            }

            $enrollments = $campaign->enrollments()->where('status', Enrollment::ACTIVE)->get()->keyBy('id');
            $checks = DailyCheck::query()
                ->whereIn('enrollment_id', $enrollments->keys())
                ->where('day_number', $dayNumber)
                ->get()
                ->keyBy('enrollment_id');

            $seats = [];
            foreach ($enrollments as $id => $enrollment) {
                $check = $checks->get($id);
                $seats[$id] = [
                    'accrued' => (int) $enrollment->accrued,
                    'misses' => (int) $enrollment->misses,
                    'report' => $check ? ['installed' => (bool) $check->installed, 'foreground_seconds' => (int) $check->foreground_seconds] : null,
                ];
            }

            $decisions = CampaignRules::closeDay($campaign->terms(), $dayNumber, $seats, $this->settings->int('min_open_seconds'));

            foreach ($decisions as $id => $decision) {
                $enrollment = $enrollments->get($id);
                $enrollment->misses = $decision['misses'];
                if ($decision['action'] === CampaignRules::ACCRUE) {
                    $enrollment->accrued += $decision['amount'];
                    $enrollment->verified_days += 1;
                } elseif ($decision['action'] === CampaignRules::REMOVE) {
                    $enrollment->status = Enrollment::REMOVED;
                    $enrollment->ended_at = now();
                    $enrollment->end_reason = $decision['reason'];
                }
                $enrollment->save();
            }

            $campaign->update([
                'days_done' => $dayNumber,
                'last_closed_on' => $campaign->started_on->addDays($dayNumber - 1)->toDateString(),
            ]);

            if ($dayNumber >= $campaign->days) {
                $this->settlement->settle($campaign->fresh());
            }
        });
    }
}
