<?php

namespace App\Services;

use App\Domain\CampaignRules;
use App\Domain\Settlement;
use App\Models\Campaign;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/** تسوية الحملة بعد آخر يوم: تحويل المكافآت أو تعليق المبلغ بانتظار قرار المطوّر. */
class SettlementService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly LedgerService $ledger,
    ) {}

    public function settle(Campaign $campaign): Settlement
    {
        return DB::transaction(function () use ($campaign) {
            $terms = $campaign->terms();
            $active = $campaign->enrollments()->where('status', Enrollment::ACTIVE)->with('tester')->get();
            $completers = $active->mapWithKeys(fn (Enrollment $e) => [$e->id => (int) $e->accrued])->all();

            $settlement = CampaignRules::settle($terms, $completers, $this->settings->bool('failure_pays_completers'));
            $appName = $campaign->androidApp->name;

            foreach ($active as $enrollment) {
                if (array_key_exists($enrollment->id, $settlement->payouts)) {
                    $enrollment->update(['status' => Enrollment::COMPLETED, 'ended_at' => now()]);
                    $this->ledger->move(
                        'payout',
                        "مكافأة اختبار «{$appName}»",
                        LedgerService::ESCROW, $campaign,
                        LedgerService::WALLET, $enrollment->tester,
                        $settlement->payouts[$enrollment->id],
                        $enrollment,
                    );
                } else {
                    $enrollment->update([
                        'status' => Enrollment::VOIDED,
                        'ended_at' => now(),
                        'end_reason' => CampaignRules::REASON_CAMPAIGN_FAILED,
                    ]);
                }
            }

            if ($settlement->success) {
                $this->ledger->move(
                    'forfeit_to_fund',
                    "حصص المنسحبين وأيام الغياب في «{$appName}»",
                    LedgerService::ESCROW, $campaign,
                    LedgerService::FUND, null,
                    $settlement->escrowRemaining,
                    $campaign,
                );
            }

            $campaign->update([
                'status' => $settlement->success ? Campaign::COMPLETED : Campaign::FAILED,
                'completers' => $settlement->completers,
                'ended_at' => now(),
            ]);

            return $settlement;
        });
    }
}
