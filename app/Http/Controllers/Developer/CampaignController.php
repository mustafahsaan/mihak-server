<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\DailyCheck;
use App\Models\Enrollment;
use App\Services\FailureService;
use App\Services\LedgerService;
use App\Services\SettingsService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function show(Request $request, Campaign $campaign, SettingsService $settings, LedgerService $ledger): View
    {
        $this->authorizeOwner($request, $campaign);
        $campaign->load(['androidApp', 'feedback' => fn ($q) => $q->latest()]);

        $enrollments = $campaign->enrollments()->orderBy('id')->get();
        $strip = $this->dayStrip($campaign, $enrollments, $settings->int('min_open_seconds'));

        return view('dev.campaign', [
            'campaign' => $campaign,
            'app' => $campaign->androidApp,
            'seats' => $enrollments,
            'strip' => $strip,
            'today' => $campaign->status === Campaign::TESTING ? $campaign->dayNumberFor(Dates::today()) : null,
            'groupEmail' => config('mihak.group_email'),
            'googleDays' => config('mihak.google_days'),
            'refundPreview' => $campaign->status === Campaign::FAILED
                ? $ledger->balance(LedgerService::ESCROW, $campaign) + ($settings->bool('refund_includes_commission') ? $campaign->terms()->platformCut() : 0)
                : null,
        ]);
    }

    public function refund(Request $request, Campaign $campaign, FailureService $failures): RedirectResponse
    {
        $this->authorizeOwner($request, $campaign);
        $refund = $failures->refund($campaign);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'سجّلنا طلب استرداد '.Dates::money($refund->amount).'. يصلك المبلغ على وسيلة الدفع نفسها.');
    }

    public function rerun(Request $request, Campaign $campaign, FailureService $failures): RedirectResponse
    {
        $this->authorizeOwner($request, $campaign);
        $next = $failures->rerun($campaign);

        return redirect()->route('campaigns.show', $next)->with('success', 'بدأت إعادة الاختبار مجاناً. نجمع الآن مختبِرين جدداً.');
    }

    private function authorizeOwner(Request $request, Campaign $campaign): void
    {
        abort_unless($campaign->androidApp->developer_id === $request->user()->id, 404);
    }

    /**
     * لكل يوم منتهٍ: كم مختبِراً فتح التطبيق من أصل الموجودين ذلك اليوم.
     *
     * @return array<int, array{day:int, opened:int, present:int, state:string}>
     */
    private function dayStrip(Campaign $campaign, $enrollments, int $minSeconds): array
    {
        if ($campaign->started_on === null) {
            return [];
        }

        $opened = DailyCheck::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->where('installed', true)
            ->where('foreground_seconds', '>=', $minSeconds)
            ->selectRaw('day_number, COUNT(*) as n')
            ->groupBy('day_number')
            ->pluck('n', 'day_number');

        $strip = [];
        for ($day = 1; $day <= $campaign->days; $day++) {
            $date = $campaign->started_on->addDays($day - 1);
            $present = $enrollments->filter(fn (Enrollment $e) => ! ($e->status === Enrollment::REMOVED && $e->ended_at && $e->ended_at->lt($date)))->count();
            $n = (int) ($opened[$day] ?? 0);
            $state = $day > $campaign->days_done
                ? ($day === $campaign->days_done + 1 && $campaign->status === Campaign::TESTING ? 'today' : 'future')
                : ($present && $n / $present >= 0.95 ? 'full' : ($present && $n / $present >= 0.8 ? 'most' : 'low'));
            $strip[] = ['day' => $day, 'opened' => $n, 'present' => $present, 'state' => $state];
        }

        return $strip;
    }
}
