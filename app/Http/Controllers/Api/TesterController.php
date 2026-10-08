<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\DailyCheck;
use App\Models\Enrollment;
use App\Models\Feedback;
use App\Models\Withdrawal;
use App\Services\CampaignService;
use App\Services\CheckService;
use App\Services\SettingsService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\Dates;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TesterController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CheckService $checks,
    ) {}

    public function me(Request $request, WalletService $wallets): JsonResponse
    {
        $user = $request->user();
        $terms = $this->settings->terms();
        $group = config('mihak.group_email');

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone, 'email' => $user->email],
            'wallet' => $wallets->summary($user),
            'rules' => [
                'share' => $terms->share,
                'daily' => $terms->daily(),
                'days' => $terms->days,
                'miss_allowed' => $terms->missAllowed,
                'min_open_seconds' => $this->settings->int('min_open_seconds'),
                'tests_to_withdraw' => $terms->testsToReach($this->settings->int('withdraw_min')),
            ],
            'group' => [
                'email' => $group,
                'url' => 'https://groups.google.com/g/'.strstr($group, '@', true),
            ],
            'completed_tests' => $user->enrollments()->where('status', Enrollment::COMPLETED)->count(),
            'today' => Dates::today()->toDateString(),
            'timezone' => Dates::tz(),
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $enrollments = $request->user()->enrollments()
            ->where('status', Enrollment::ACTIVE)
            ->whereHas('campaign', fn ($q) => $q->whereIn('status', [Campaign::RECRUITING, Campaign::TESTING]))
            ->with(['campaign.androidApp', 'checks'])
            ->latest('id')
            ->get();

        return response()->json([
            'today' => Dates::today()->toDateString(),
            'enrollments' => $enrollments->map(fn (Enrollment $e) => $this->enrollmentDto($e))->values(),
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        $user = $request->user();
        $campaigns = Campaign::query()
            ->where('status', Campaign::RECRUITING)
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('tester_id', $user->id))
            ->withCount(['enrollments as taken' => fn ($q) => $q->where('status', Enrollment::ACTIVE)])
            ->with('androidApp')
            ->oldest('paid_at')
            ->limit(50)
            ->get()
            ->filter(fn (Campaign $c) => $c->taken < $c->slots);

        return response()->json([
            'campaigns' => $campaigns->map(fn (Campaign $c) => [
                'id' => $c->id,
                'app_name' => $c->androidApp->name,
                'package_name' => $c->androidApp->package_name,
                'share' => $c->share,
                'daily' => $c->terms()->daily(),
                'days' => $c->days,
                'slots' => $c->slots,
                'seats_left' => $c->slots - $c->taken,
                'cycle' => $c->cycle,
            ])->values(),
        ]);
    }

    public function join(Request $request, Campaign $campaign, CampaignService $service): JsonResponse
    {
        $enrollment = $service->join($campaign, $request->user());

        return response()->json(['enrollment' => $this->enrollmentDto($enrollment->load('campaign.androidApp', 'checks'))], 201);
    }

    public function confirmInstall(Request $request, Enrollment $enrollment, CampaignService $service): JsonResponse
    {
        $this->own($request, $enrollment);
        $enrollment = $service->confirmInstall($enrollment);

        return response()->json(['enrollment' => $this->enrollmentDto($enrollment->load('campaign.androidApp', 'checks'))]);
    }

    public function leave(Request $request, Enrollment $enrollment, CampaignService $service): JsonResponse
    {
        $this->own($request, $enrollment);

        return response()->json(['result' => $service->leave($enrollment)]);
    }

    public function feedback(Request $request, Enrollment $enrollment): JsonResponse
    {
        $this->own($request, $enrollment);
        $data = $request->validate(['body' => ['required', 'string', 'min:5', 'max:2000']], ['body.min' => 'اكتب ملاحظة من 5 أحرف على الأقل.']);

        Feedback::query()->create([
            'enrollment_id' => $enrollment->id,
            'campaign_id' => $enrollment->campaign_id,
            'tester_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json(['message' => 'وصلت ملاحظتك إلى المطوّر.'], 201);
    }

    /** تقارير الاستخدام اليومية من الهاتف (دفعة واحدة لكل الاختبارات الجارية) */
    public function checks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'checks' => ['required', 'array', 'max:50'],
            'checks.*.enrollment_id' => ['required', 'integer'],
            'checks.*.date' => ['required', 'date_format:Y-m-d'],
            'checks.*.installed' => ['required', 'boolean'],
            'checks.*.foreground_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $results = [];
        foreach ($data['checks'] as $row) {
            $enrollment = Enrollment::query()->with('campaign')->where('tester_id', $request->user()->id)->find($row['enrollment_id']);
            if (! $enrollment) {
                $results[] = ['enrollment_id' => $row['enrollment_id'], 'ok' => false, 'message' => 'مقعد غير موجود.'];

                continue;
            }
            try {
                $check = $this->checks->record($enrollment, CarbonImmutable::parse($row['date'], Dates::tz()), (bool) $row['installed'], (int) $row['foreground_seconds']);
                $results[] = ['enrollment_id' => $enrollment->id, 'ok' => true, 'day_number' => $check->day_number, 'opened' => $this->checks->isOpened($check)];
            } catch (RuleViolation $e) {
                $results[] = ['enrollment_id' => $enrollment->id, 'ok' => false, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function history(Request $request, WalletService $wallets): JsonResponse
    {
        return response()->json(['items' => $wallets->history($request->user())]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        return response()->json([
            'withdrawals' => $request->user()->withdrawals()->latest('id')->limit(30)->get()->map(fn (Withdrawal $w) => [
                'id' => $w->id,
                'amount' => $w->amount,
                'method' => $w->method,
                'account' => $w->account,
                'status' => $w->status,
                'status_label' => $w->label(),
                'note' => $w->admin_note,
                'created_at' => $w->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function requestWithdrawal(Request $request, WithdrawalService $service, WalletService $wallets): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(array_keys(Withdrawal::METHODS))],
            'account' => ['required', 'string', 'max:40'],
        ]);

        $withdrawal = $service->request($request->user(), (int) $data['amount'], $data['method'], $data['account']);

        return response()->json([
            'message' => 'أُرسل طلب السحب. تراجعه الإدارة ثم يُحوَّل المبلغ.',
            'withdrawal_id' => $withdrawal->id,
            'wallet' => $wallets->summary($request->user()),
        ], 201);
    }

    private function own(Request $request, Enrollment $enrollment): void
    {
        abort_unless($enrollment->tester_id === $request->user()->id, 404);
    }

    /** @return array<string, mixed> */
    private function enrollmentDto(Enrollment $e): array
    {
        $campaign = $e->campaign;
        $app = $campaign->androidApp;
        $today = Dates::today();
        $day = $campaign->status === Campaign::TESTING ? $campaign->dayNumberFor($today) : null;
        /** @var DailyCheck|null $check */
        $check = $day ? $e->checks->firstWhere('day_number', $day) : null;
        $deadline = $e->installed_confirmed_at === null
            ? $e->joined_at->copy()->addHours($this->settings->int('seat_confirm_hours'))->toIso8601String()
            : null;

        return [
            'id' => $e->id,
            'status' => $e->status,
            'accrued' => (int) $e->accrued,
            'misses' => (int) $e->misses,
            'miss_allowed' => $campaign->miss_allowed,
            'installed_confirmed' => $e->installed_confirmed_at !== null,
            'confirm_deadline' => $deadline,
            'day_number' => $day,
            'days_done' => $campaign->days_done,
            'starts_on' => $campaign->started_on?->toDateString(),
            'opened_today' => $this->checks->isOpened($check),
            'seconds_today' => (int) ($check?->foreground_seconds ?? 0),
            'campaign' => [
                'id' => $campaign->id,
                'status' => $campaign->status,
                'app_name' => $app->name,
                'package_name' => $app->package_name,
                'opt_in_url' => $app->optInUrl(),
                'play_url' => $app->playStoreUrl(),
                'share' => $campaign->share,
                'daily' => $campaign->terms()->daily(),
                'days' => $campaign->days,
                'slots' => $campaign->slots,
            ],
        ];
    }
}
