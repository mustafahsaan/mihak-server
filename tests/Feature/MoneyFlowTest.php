<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CampaignService;
use App\Services\CheckService;
use App\Services\DayCloseService;
use App\Services\FailureService;
use App\Services\LedgerService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\Dates;
use App\Support\RuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * يمر على الدورة المالية كاملة: دفع ← انضمام ← 16 يوماً ← تسوية ← سحب، ومسار الفشل.
 * التشغيل: php artisan test
 */
class MoneyFlowTest extends TestCase
{
    use RefreshDatabase;

    private CampaignService $campaigns;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campaigns = app(CampaignService::class);
        $this->ledger = app(LedgerService::class);
    }

    private function developer(): User
    {
        return User::query()->create(['name' => 'مطوّر', 'phone' => '07700000001', 'password' => 'secret123', 'role' => User::DEVELOPER, 'phone_verified_at' => now()]);
    }

    private function tester(int $i): User
    {
        return User::query()->create([
            'name' => "مختبِر {$i}", 'phone' => sprintf('078%08d', $i), 'email' => "t{$i}@gmail.com",
            'password' => 'secret123', 'role' => User::TESTER, 'device_id' => "device-{$i}", 'phone_verified_at' => now(),
        ]);
    }

    private function paidCampaign(User $developer): Campaign
    {
        $app = $developer->apps()->create(['name' => 'تطبيق', 'package_name' => 'com.test.app'.Str::random(4)]);
        $campaign = $this->campaigns->createFor($app);
        $payment = $campaign->payments()->create([
            'user_id' => $developer->id, 'gateway' => 'zaincash', 'amount' => $campaign->price,
            'status' => Payment::PENDING, 'external_ref' => (string) Str::uuid(), 'gateway_txn_id' => 'T-'.$campaign->id,
        ]);
        $this->assertTrue($this->campaigns->markPaid($payment));
        $this->assertFalse($this->campaigns->markPaid($payment), 'تأكيد الدفعة مرتين لا يكرر الحركة');

        return $campaign->fresh();
    }

    /** @return \Illuminate\Support\Collection<int, Enrollment> */
    private function fill(Campaign $campaign, int $from = 1): \Illuminate\Support\Collection
    {
        return collect(range($from, $from + $campaign->slots - 1))->map(function (int $i) use ($campaign) {
            $enrollment = $this->campaigns->join($campaign, $this->tester($i));

            return $this->campaigns->confirmInstall($enrollment);
        });
    }

    /** يحاكي يوماً: كل المقاعد تفتح التطبيق إلا المستثناة، ثم يُغلق اليوم */
    private function runDay(Campaign $campaign, array $skipEnrollmentIds = []): void
    {
        $campaign->refresh();
        $day = $campaign->days_done + 1;
        $campaign->update(['started_on' => Dates::today()->subDays($day - 1)->toDateString()]);
        $campaign->refresh();

        foreach ($campaign->enrollments()->where('status', Enrollment::ACTIVE)->get() as $e) {
            if (! in_array($e->id, $skipEnrollmentIds, true)) {
                app(CheckService::class)->record($e, Dates::today(), true, 120);
            }
        }
        app(DayCloseService::class)->closeDay($campaign, $day);
    }

    private function assertBalanced(): void
    {
        $report = app(WalletService::class)->integrity();
        $this->assertTrue($report['ok'], json_encode($report['checks'], JSON_UNESCAPED_UNICODE));
    }

    public function test_successful_campaign_pays_testers_and_respects_withdraw_minimum(): void
    {
        $campaign = $this->paidCampaign($this->developer());
        $this->assertSame(Campaign::RECRUITING, $campaign->status);
        $this->assertSame(15988, $this->ledger->balance(LedgerService::ESCROW, $campaign));
        $this->assertSame(4012, $this->ledger->balance(LedgerService::REVENUE));

        $seats = $this->fill($campaign);
        $campaign->refresh();
        $this->assertSame(Campaign::TESTING, $campaign->status);

        $leaver = $seats[0];
        $absent = $seats[1];
        for ($day = 1; $day <= 16; $day++) {
            if ($day === 5) {
                $this->assertSame('forfeit', $this->campaigns->leave($leaver->fresh()));
            }
            $this->runDay($campaign, $day === 3 ? [$absent->id] : []);
        }

        $campaign->refresh();
        $this->assertSame(Campaign::COMPLETED, $campaign->status);
        $this->assertSame(13, $campaign->completers);

        $full = $seats[2]->tester;
        $this->assertSame(1142, $this->ledger->balance(LedgerService::WALLET, $full));
        $this->assertSame(1142 - 71, $this->ledger->balance(LedgerService::WALLET, $absent->tester));
        $this->assertSame(0, $this->ledger->balance(LedgerService::WALLET, $leaver->tester));
        $this->assertSame(0, $this->ledger->balance(LedgerService::ESCROW, $campaign));
        $this->assertSame(1142 + 71, $this->ledger->balance(LedgerService::FUND));

        $this->expectException(RuleViolation::class);
        app(WithdrawalService::class)->request($full, 1142, 'zaincash', '07801234567');
    }

    public function test_withdrawal_after_reaching_minimum(): void
    {
        $developer = $this->developer();
        $tester = $this->tester(500);
        // 9 حملات ناجحة تكفي للوصول إلى 10,000
        for ($n = 0; $n < 9; $n++) {
            $campaign = $this->paidCampaign($developer);
            $this->campaigns->confirmInstall($this->campaigns->join($campaign, $tester));
            $this->fillOthers($campaign, 1000 + $n * 20);
            for ($day = 1; $day <= 16; $day++) {
                $this->runDay($campaign);
            }
        }
        $this->assertSame(9 * 1142, $this->ledger->balance(LedgerService::WALLET, $tester));

        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($tester, 10000, 'zaincash', '07801234567');
        $this->assertSame(278, $this->ledger->balance(LedgerService::WALLET, $tester));
        $this->assertBalanced();

        $admin = User::query()->create(['name' => 'admin', 'phone' => '07700000009', 'password' => 'x', 'role' => User::ADMIN, 'phone_verified_at' => now()]);
        $service->approve($withdrawal, $admin, 'TRX-1');
        $this->assertSame(Withdrawal::PAID, $withdrawal->fresh()->status);
        $this->assertBalanced();
    }

    private function fillOthers(Campaign $campaign, int $from): void
    {
        $campaign->refresh();
        $i = $from;
        while ($campaign->fresh()->status === Campaign::RECRUITING) {
            $this->campaigns->confirmInstall($this->campaigns->join($campaign, $this->tester($i++)));
        }
    }

    public function test_failed_campaign_pays_nobody_and_refunds_developer_in_full(): void
    {
        $campaign = $this->paidCampaign($this->developer());
        $seats = $this->fill($campaign);

        // 3 مختبِرين ينسحبون: يبقى 11 من 12 المطلوبين
        foreach ($seats->take(3) as $seat) {
            $this->campaigns->leave($seat->fresh());
        }
        for ($day = 1; $day <= 16; $day++) {
            $this->runDay($campaign);
        }

        $campaign->refresh();
        $this->assertSame(Campaign::FAILED, $campaign->status);
        $this->assertSame(11, $campaign->completers);
        $this->assertSame(0, $this->ledger->balance(LedgerService::WALLET, $seats[5]->tester), 'لا يُدفع لأحد عند الفشل');
        $this->assertSame(Enrollment::VOIDED, $seats[5]->fresh()->status);

        $refund = app(FailureService::class)->refund($campaign);
        $this->assertSame(20000, $refund->amount);
        $this->assertSame(0, $this->ledger->balance(LedgerService::REVENUE));
        $this->assertBalanced();
    }

    public function test_failed_campaign_can_rerun_for_free(): void
    {
        $campaign = $this->paidCampaign($this->developer());
        $seats = $this->fill($campaign);
        foreach ($seats->take(3) as $seat) {
            $this->campaigns->leave($seat->fresh());
        }
        for ($day = 1; $day <= 16; $day++) {
            $this->runDay($campaign);
        }

        $next = app(FailureService::class)->rerun($campaign->fresh());
        $this->assertSame(Campaign::RECRUITING, $next->status);
        $this->assertSame(2, $next->cycle);
        $this->assertSame(15988, $this->ledger->balance(LedgerService::ESCROW, $next));
        $this->assertSame(0, $this->ledger->balance(LedgerService::ESCROW, $campaign));

        // من خرج من الدورة السابقة لا ينضم للإعادة
        $this->expectException(RuleViolation::class);
        $this->campaigns->join($next, $seats[0]->tester);
    }

    public function test_api_flow_for_tester(): void
    {
        $campaign = $this->paidCampaign($this->developer());

        $this->postJson('/api/auth/register', [
            'name' => 'علي', 'phone' => '07811111111', 'email' => 'ali@gmail.com', 'password' => 'secret123', 'device_id' => 'device-api-1',
        ])->assertCreated();

        $user = User::query()->where('phone', '07811111111')->first();
        $user->update(['phone_verified_at' => now()]);
        $token = $this->postJson('/api/auth/login', ['phone' => '07811111111', 'password' => 'secret123', 'device_id' => 'device-api-1'])
            ->assertOk()->json('token');

        $headers = ['Authorization' => 'Bearer '.$token, 'X-Device-Id' => 'device-api-1'];
        $this->getJson('/api/campaigns/available', $headers)->assertOk()->assertJsonPath('campaigns.0.id', $campaign->id);
        $enrollmentId = $this->postJson("/api/campaigns/{$campaign->id}/join", [], $headers)->assertCreated()->json('enrollment.id');
        $this->postJson("/api/enrollments/{$enrollmentId}/confirm-install", [], $headers)->assertOk();
        $this->getJson('/api/me', $headers)->assertOk()->assertJsonPath('wallet.withdraw_min', 10000);

        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token, 'X-Device-Id' => 'other-device'])->assertForbidden();
    }

    public function test_registration_without_otp_returns_token_directly(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'زينب', 'phone' => '07822222222', 'email' => 'zainab@gmail.com', 'password' => 'secret123', 'device_id' => 'device-api-2',
        ])->assertCreated()->assertJsonPath('otp_required', false);

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotNull(User::query()->where('phone', '07822222222')->value('phone_verified_at'));
        $this->assertSame(0, \App\Models\OtpCode::query()->count(), 'لا تُرسل رسائل والرمز موقوف');
    }

    public function test_otp_when_enabled_requires_code_and_respects_resend_countdown(): void
    {
        app(\App\Services\SettingsService::class)->put(['otp_enabled' => true, 'otp_resend_seconds' => 60]);
        $sms = new class implements \App\Services\Sms\SmsSender
        {
            public array $sent = [];

            public function send(string $phone, string $message): void
            {
                $this->sent[] = $message;
            }
        };
        $this->app->instance(\App\Services\Sms\SmsSender::class, $sms);

        $this->postJson('/api/auth/register', [
            'name' => 'حسن', 'phone' => '07833333333', 'email' => 'hasan@gmail.com', 'password' => 'secret123', 'device_id' => 'device-api-3',
        ])->assertCreated()->assertJsonPath('otp_required', true)->assertJsonPath('resend_after', 60);
        $this->assertCount(1, $sms->sent);

        // قبل انتهاء العداد يُرفض طلب رمز جديد
        $this->postJson('/api/auth/resend', ['phone' => '07833333333'])->assertStatus(422);

        $this->travel(61)->seconds();
        $this->postJson('/api/auth/resend', ['phone' => '07833333333'])->assertOk();
        $this->assertCount(2, $sms->sent);

        preg_match('/\d{6}/', end($sms->sent), $m);
        $this->postJson('/api/auth/verify', ['phone' => '07833333333', 'code' => $m[0], 'device_id' => 'device-api-3'])
            ->assertOk()->assertJsonStructure(['token']);
    }
}
