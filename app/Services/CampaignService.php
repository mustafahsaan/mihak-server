<?php

namespace App\Services;

use App\Domain\CampaignRules;
use App\Models\AndroidApp;
use App\Models\Campaign;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Support\Dates;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly LedgerService $ledger,
    ) {}

    /** حملة جديدة غير مدفوعة بشروط الإعدادات الحالية */
    public function createFor(AndroidApp $app): Campaign
    {
        $open = $app->campaigns()->whereIn('status', [Campaign::UNPAID, Campaign::RECRUITING, Campaign::TESTING, Campaign::FAILED])->first();
        if ($open) {
            return $open;
        }

        return $app->campaigns()->create($this->settings->terms()->toColumns() + [
            'status' => Campaign::UNPAID,
            'cycle' => 1,
        ]);
    }

    /**
     * تسجيل دفعة ناجحة. آمنة للتكرار: رابط الرجوع والإشعار والتسوية الدورية قد تصل كلها لنفس الدفعة.
     */
    public function markPaid(Payment $payment, array $gatewayData = []): bool
    {
        return DB::transaction(function () use ($payment, $gatewayData) {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($payment->status === Payment::PAID) {
                return false;
            }

            /** @var Campaign $campaign */
            $campaign = Campaign::query()->whereKey($payment->campaign_id)->lockForUpdate()->firstOrFail();

            $payment->fill(['status' => Payment::PAID, 'paid_at' => now()]);
            $payment->payload = array_merge($payment->payload ?? [], ['confirmed' => $gatewayData]);
            $payment->save();

            if ($campaign->status !== Campaign::UNPAID) {
                // دفعة ثانية لحملة مدفوعة مسبقاً: تُحجز لإعادتها للمطوّر
                Log::warning('Duplicate payment received', ['payment' => $payment->id, 'campaign' => $campaign->id]);
                $refund = Refund::query()->create([
                    'campaign_id' => $campaign->id,
                    'user_id' => $payment->user_id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'reason' => 'duplicate_payment',
                    'status' => Refund::PENDING,
                ]);
                $this->ledger->move('duplicate_payment', "دفعة مكررة للحملة #{$campaign->id}", LedgerService::EXTERNAL, null, LedgerService::REFUND_HOLD, $payment->user, $payment->amount, $refund);

                return true;
            }

            $terms = $campaign->terms();
            $this->ledger->post('payment', "دفع «{$campaign->androidApp->name}» عبر {$payment->gatewayLabel()}", [
                [LedgerService::EXTERNAL, null, -$payment->amount],
                [LedgerService::REVENUE, null, $terms->platformCut()],
                [LedgerService::ESCROW, $campaign, $terms->escrow()],
            ], $payment);

            $campaign->update(['status' => Campaign::RECRUITING, 'paid_at' => now()]);

            return true;
        });
    }

    public function join(Campaign $campaign, User $tester): Enrollment
    {
        if (! $tester->isTester()) {
            throw new RuleViolation('الانضمام متاح لحسابات المختبِرين فقط.');
        }

        return DB::transaction(function () use ($campaign, $tester) {
            /** @var Campaign $campaign */
            $campaign = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();

            if ($campaign->status !== Campaign::RECRUITING) {
                throw new RuleViolation('هذا الاختبار لا يستقبل مختبِرين الآن.');
            }
            if ($campaign->enrollments()->where('tester_id', $tester->id)->exists()) {
                throw new RuleViolation('أنت منضم لهذا الاختبار مسبقاً.');
            }
            if ($campaign->parent_campaign_id && Enrollment::query()
                ->where('campaign_id', $campaign->parent_campaign_id)
                ->where('tester_id', $tester->id)
                ->where('status', Enrollment::REMOVED)->exists()) {
                throw new RuleViolation('لا يمكنك الانضمام لإعادة اختبار خرجت من دورته السابقة.');
            }
            if ($campaign->activeCount() >= $campaign->slots) {
                throw new RuleViolation('امتلأت المقاعد.');
            }

            return $campaign->enrollments()->create([
                'tester_id' => $tester->id,
                'status' => Enrollment::ACTIVE,
                'joined_at' => now(),
            ]);
        });
    }

    /** الهاتف يؤكد أن التطبيق مثبّت. حين تكتمل كل المقاعد المؤكدة يبدأ الاختبار من اليوم التالي. */
    public function confirmInstall(Enrollment $enrollment): Enrollment
    {
        return DB::transaction(function () use ($enrollment) {
            /** @var Campaign $campaign */
            $campaign = Campaign::query()->whereKey($enrollment->campaign_id)->lockForUpdate()->firstOrFail();
            $enrollment->refresh();

            if ($enrollment->status !== Enrollment::ACTIVE) {
                throw new RuleViolation('هذا المقعد لم يعد نشطاً.');
            }
            if ($enrollment->installed_confirmed_at === null) {
                $enrollment->update(['installed_confirmed_at' => now()]);
            }

            $this->startIfReady($campaign);

            return $enrollment->fresh();
        });
    }

    private function startIfReady(Campaign $campaign): void
    {
        if ($campaign->status !== Campaign::RECRUITING) {
            return;
        }
        $confirmed = $campaign->enrollments()
            ->where('status', Enrollment::ACTIVE)
            ->whereNotNull('installed_confirmed_at')
            ->count();

        if ($confirmed >= $campaign->slots) {
            // اليوم الأول يبدأ غداً، حتى لا يخسر أحد يوماً اكتمل فيه العدد آخر الليل
            $campaign->update([
                'status' => Campaign::TESTING,
                'started_on' => Dates::today()->addDay()->toDateString(),
                'days_done' => 0,
            ]);
        }
    }

    /**
     * انسحاب المختبِر. قبل بدء الاختبار يُحذف المقعد بلا خسارة.
     * بعد البدء يخرج ويخسر المعلّق، ويبقى المال في ضمان الحملة حتى التسوية.
     *
     * @return 'free'|'forfeit'
     */
    public function leave(Enrollment $enrollment): string
    {
        return DB::transaction(function () use ($enrollment) {
            Campaign::query()->whereKey($enrollment->campaign_id)->lockForUpdate()->firstOrFail();
            $enrollment->refresh();
            $campaign = $enrollment->campaign;

            if ($enrollment->status !== Enrollment::ACTIVE) {
                throw new RuleViolation('هذا المقعد لم يعد نشطاً.');
            }

            if ($campaign->status === Campaign::RECRUITING) {
                $enrollment->delete();

                return 'free';
            }

            $enrollment->update([
                'status' => Enrollment::REMOVED,
                'ended_at' => now(),
                'end_reason' => CampaignRules::REASON_LEFT,
            ]);

            return 'forfeit';
        });
    }

    /** يحرر المقاعد التي لم يؤكد أصحابها التثبيت خلال المهلة */
    public function releaseUnconfirmedSeats(): int
    {
        $hours = $this->settings->int('seat_confirm_hours');

        return Enrollment::query()
            ->where('status', Enrollment::ACTIVE)
            ->whereNull('installed_confirmed_at')
            ->where('joined_at', '<', now()->subHours($hours))
            ->whereHas('campaign', fn ($q) => $q->where('status', Campaign::RECRUITING))
            ->delete();
    }
}
