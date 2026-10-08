<?php

namespace Tests\Unit;

use App\Domain\CampaignRules;
use App\Domain\Terms;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CampaignRulesTest extends TestCase
{
    private function defaults(array $override = []): Terms
    {
        return Terms::fromSettings(array_merge([
            'price' => 20000,
            'commission_percent' => 20,
            'required_testers' => 12,
            'reserve_testers' => 2,
            'test_days' => 16,
            'miss_allowed' => 2,
        ], $override));
    }

    public function test_default_pricing_splits_twenty_thousand(): void
    {
        $t = $this->defaults();

        $this->assertSame(1142, $t->share);
        $this->assertSame(14, $t->slots);
        $this->assertSame(15988, $t->escrow());
        $this->assertSame(4012, $t->platformCut());
        $this->assertSame(71, $t->daily());
        $this->assertSame(77, $t->lastDay());
        $this->assertSame(9, $t->testsToReach(10000));
        $this->assertSame($t->price, $t->escrow() + $t->platformCut());
    }

    public function test_daily_amounts_add_up_to_the_share_exactly(): void
    {
        foreach ([[20000, 20, 16], [25000, 0, 18], [15000, 15, 14], [20000, 10, 16]] as [$price, $commission, $days]) {
            $t = $this->defaults(['price' => $price, 'commission_percent' => $commission, 'test_days' => $days]);
            $sum = 0;
            for ($d = 1; $d <= $t->days; $d++) {
                $sum += $t->amountForDay($d);
            }
            $this->assertSame($t->share, $sum, "price {$price} commission {$commission} days {$days}");
        }
    }

    public function test_day_outside_the_test_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->defaults()->amountForDay(17);
    }

    public function test_close_day_accrues_misses_and_removes(): void
    {
        $t = $this->defaults();
        $seats = [
            1 => ['accrued' => 0, 'misses' => 0, 'report' => ['installed' => true, 'foreground_seconds' => 45]],
            2 => ['accrued' => 0, 'misses' => 0, 'report' => ['installed' => true, 'foreground_seconds' => 5]],
            3 => ['accrued' => 0, 'misses' => 2, 'report' => null],
            4 => ['accrued' => 0, 'misses' => 0, 'report' => ['installed' => false, 'foreground_seconds' => 300]],
        ];

        $d = CampaignRules::closeDay($t, 16, $seats, 30);

        $this->assertSame(['action' => 'accrue', 'amount' => 77, 'misses' => 0, 'reason' => null], $d[1]);
        $this->assertSame('miss', $d[2]['action']);
        $this->assertSame(1, $d[2]['misses']);
        $this->assertSame('remove', $d[3]['action']);
        $this->assertSame(CampaignRules::REASON_TOO_MANY_MISSES, $d[3]['reason']);
        $this->assertSame(CampaignRules::REASON_UNINSTALLED, $d[4]['reason']);
    }

    public function test_successful_campaign_pays_completers_and_sends_rest_to_fund(): void
    {
        $t = $this->defaults();
        $completers = array_fill(1, 13, $t->share); // 13 أكملوا وواحد خرج
        $completers[5] = $t->share - $t->daily();   // غاب يوماً واحداً

        $s = CampaignRules::settle($t, $completers, false);

        $this->assertTrue($s->success);
        $this->assertSame(13, $s->completers);
        $this->assertSame(array_sum($completers), $s->totalPaid());
        $this->assertSame($t->escrow() - array_sum($completers), $s->escrowRemaining);
        $this->assertSame($t->share + $t->daily(), $s->escrowRemaining);
    }

    public function test_failed_campaign_pays_nobody_by_default(): void
    {
        $t = $this->defaults();
        $completers = array_fill(1, 11, $t->share);

        $s = CampaignRules::settle($t, $completers, false);

        $this->assertFalse($s->success);
        $this->assertSame([], $s->payouts);
        $this->assertSame($t->escrow(), $s->escrowRemaining);
        $this->assertSame(20000, CampaignRules::refundAmount($t, $s->escrowRemaining, true));
        $this->assertSame($t->escrow(), CampaignRules::refundAmount($t, $s->escrowRemaining, false));
    }

    public function test_failed_campaign_can_pay_completers_when_enabled(): void
    {
        $t = $this->defaults();
        $s = CampaignRules::settle($t, array_fill(1, 11, $t->share), true);

        $this->assertFalse($s->success);
        $this->assertSame(11 * $t->share, $s->totalPaid());
        $this->assertSame(3 * $t->share, $s->escrowRemaining);
    }

    public function test_rerun_uses_failed_escrow_then_fund_then_revenue(): void
    {
        $t = $this->defaults();

        $full = CampaignRules::rerunFunding($t, $t->escrow(), 0);
        $this->assertSame(['need' => 15988, 'from_escrow' => 15988, 'from_fund' => 0, 'from_revenue' => 0, 'escrow_leftover' => 0], $full);

        $partial = CampaignRules::rerunFunding($t, 3 * $t->share, 5000);
        $this->assertSame(3426, $partial['from_escrow']);
        $this->assertSame(5000, $partial['from_fund']);
        $this->assertSame(15988 - 3426 - 5000, $partial['from_revenue']);
    }

    public function test_withdrawal_rules(): void
    {
        $this->assertSame('below_minimum', CampaignRules::withdrawalError(9995, 9995, 10000));
        $this->assertSame('insufficient_balance', CampaignRules::withdrawalError(10200, 10500, 10000));
        $this->assertNull(CampaignRules::withdrawalError(10278, 10000, 10000));
        $this->assertNull(CampaignRules::withdrawalError(10278, 10278, 10000));
    }

    public function test_full_campaign_simulation_never_overpays(): void
    {
        mt_srand(7);
        for ($run = 0; $run < 300; $run++) {
            $t = $this->defaults();
            $seats = [];
            for ($i = 1; $i <= $t->slots; $i++) {
                $seats[$i] = ['accrued' => 0, 'misses' => 0, 'active' => true];
            }
            for ($day = 1; $day <= $t->days; $day++) {
                $input = [];
                foreach ($seats as $id => $s) {
                    if (! $s['active']) {
                        continue;
                    }
                    $roll = mt_rand(1, 1000);
                    $report = $roll <= 4 ? ['installed' => false, 'foreground_seconds' => 0]
                        : ($roll <= 40 ? null : ['installed' => true, 'foreground_seconds' => mt_rand(0, 600)]);
                    $input[$id] = ['accrued' => $s['accrued'], 'misses' => $s['misses'], 'report' => $report];
                }
                foreach (CampaignRules::closeDay($t, $day, $input, 30) as $id => $d) {
                    $seats[$id]['misses'] = $d['misses'];
                    $seats[$id]['accrued'] += $d['amount'];
                    if ($d['action'] === 'remove') {
                        $seats[$id]['active'] = false;
                    }
                }
            }
            $completers = [];
            foreach ($seats as $id => $s) {
                $this->assertLessThanOrEqual($t->share, $s['accrued']);
                $this->assertLessThanOrEqual($t->missAllowed + 1, $s['misses']);
                if ($s['active']) {
                    $completers[$id] = $s['accrued'];
                }
            }
            $settlement = CampaignRules::settle($t, $completers, false);
            $this->assertGreaterThanOrEqual(0, $settlement->escrowRemaining);
            $this->assertSame($t->escrow(), $settlement->totalPaid() + $settlement->escrowRemaining);
        }
    }
}
