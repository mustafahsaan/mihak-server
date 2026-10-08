<?php

namespace App\Services;

use App\Domain\CampaignRules;
use App\Models\Campaign;
use App\Models\Refund;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;

/** قرار المطوّر بعد حملة لم يكتمل عددها: استرداد المبلغ أو إعادة الاختبار مجاناً. */
class FailureService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly LedgerService $ledger,
    ) {}

    public function refund(Campaign $campaign): Refund
    {
        return DB::transaction(function () use ($campaign) {
            $campaign = $this->lockFailed($campaign);
            $terms = $campaign->terms();
            $remaining = $this->ledger->balance(LedgerService::ESCROW, $campaign);
            // العمولة أُخذت مرة واحدة عند الدفع الأصلي، وإعادة الاختبار لا تأخذ عمولة جديدة،
            // لذلك تُعاد مرة واحدة فقط عند الاسترداد مهما كان رقم الدورة.
            $includeCut = $this->settings->bool('refund_includes_commission');
            $cut = $includeCut ? $terms->platformCut() : 0;
            $amount = CampaignRules::refundAmount($terms, $remaining, $includeCut);
            $developer = $campaign->androidApp->developer;

            $refund = Refund::query()->create([
                'campaign_id' => $campaign->id,
                'user_id' => $developer->id,
                'payment_id' => $campaign->originPayment()?->id,
                'amount' => $amount,
                'reason' => 'campaign_failed',
                'status' => Refund::PENDING,
            ]);

            $this->ledger->post('refund_reserved', "استرداد «{$campaign->androidApp->name}» للمطوّر", [
                [LedgerService::ESCROW, $campaign, -$remaining],
                [LedgerService::REVENUE, null, -$cut],
                [LedgerService::REFUND_HOLD, $developer, $amount],
            ], $refund);

            $campaign->update(['status' => Campaign::REFUNDED, 'failure_choice' => 'refund']);

            return $refund;
        });
    }

    public function rerun(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign) {
            $campaign = $this->lockFailed($campaign);
            $terms = $campaign->terms();
            $remaining = $this->ledger->balance(LedgerService::ESCROW, $campaign);
            $funding = CampaignRules::rerunFunding($terms, $remaining, $this->ledger->balance(LedgerService::FUND));

            /** @var Campaign $next */
            $next = Campaign::query()->create($terms->toColumns() + [
                'android_app_id' => $campaign->android_app_id,
                'parent_campaign_id' => $campaign->id,
                'cycle' => $campaign->cycle + 1,
                'status' => Campaign::RECRUITING,
                'paid_at' => now(),
            ]);

            $this->ledger->post('rerun_funding', "إعادة اختبار «{$campaign->androidApp->name}» مجاناً (الدورة {$next->cycle})", [
                [LedgerService::ESCROW, $campaign, -$funding['from_escrow']],
                [LedgerService::FUND, null, -$funding['from_fund']],
                [LedgerService::REVENUE, null, -$funding['from_revenue']],
                [LedgerService::ESCROW, $next, $funding['need']],
            ], $next);

            $campaign->update(['status' => Campaign::RERUN, 'failure_choice' => 'rerun']);

            return $next;
        });
    }

    private function lockFailed(Campaign $campaign): Campaign
    {
        /** @var Campaign $locked */
        $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();
        if ($locked->status !== Campaign::FAILED) {
            throw new RuleViolation('اتُّخذ القرار لهذه الحملة مسبقاً.');
        }

        return $locked;
    }
}
