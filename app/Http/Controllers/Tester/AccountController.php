<?php

namespace App\Http\Controllers\Tester;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CheckService;
use App\Services\OtpService;
use App\Services\SettingsService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\Dates;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * المختبِر في الموقع: التسجيل، الرصيد، الاختبارات، السجل، وطلب السحب.
 * الانضمام للاختبارات والتحقق اليومي يبقيان في تطبيق الهاتف لأنهما يحتاجان الجهاز نفسه.
 */
class AccountController extends Controller
{
    public function showRegister(SettingsService $settings): View
    {
        return view('tester.register', ['terms' => $settings->terms(), 'withdrawMin' => $settings->int('withdraw_min')]);
    }

    public function register(Request $request, OtpService $otp): RedirectResponse
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone'),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'regex:/^07\d{9}$/', 'unique:users,phone'],
            'email' => ['required', 'email', 'max:120', 'ends_with:@gmail.com', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone.regex' => 'اكتب رقماً عراقياً من 11 رقماً يبدأ بـ 07.',
            'phone.unique' => 'هذا الرقم مسجّل مسبقاً. سجّل الدخول.',
            'email.ends_with' => 'استخدم حساب Gmail، لأن Google Play يربط الاختبار به.',
            'email.unique' => 'حساب Gmail هذا مستخدم في حساب آخر.',
            'password.min' => 'كلمة المرور 8 أحرف على الأقل.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
        ]);

        // بدون معرّف جهاز: يُربط الحساب بأول هاتف يسجّل الدخول منه في التطبيق
        $user = User::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::TESTER,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        if ($otp->autoVerifyIfDisabled($user)) {
            return redirect()->route('tester.dashboard')->with('success', 'أُنشئ حسابك. حمّل التطبيق وسجّل الدخول بنفس الرقم لتبدأ الاختبارات.');
        }
        $otp->send($user->phone);

        return redirect()->route('otp.show');
    }

    public function dashboard(Request $request, WalletService $wallets, SettingsService $settings, CheckService $checks): View
    {
        $user = $request->user();
        $today = Dates::today();
        $terms = $settings->terms();

        $enrollments = $user->enrollments()
            ->where('status', Enrollment::ACTIVE)
            ->whereHas('campaign', fn ($q) => $q->whereIn('status', [Campaign::RECRUITING, Campaign::TESTING]))
            ->with(['campaign.androidApp', 'checks'])
            ->latest('id')
            ->get()
            ->map(function (Enrollment $e) use ($today, $checks) {
                $day = $e->campaign->status === Campaign::TESTING ? $e->campaign->dayNumberFor($today) : null;

                return [
                    'enrollment' => $e,
                    'campaign' => $e->campaign,
                    'day' => $day,
                    'opened' => $day ? $checks->isOpened($e->checks->firstWhere('day_number', $day)) : false,
                ];
            });

        $available = Campaign::query()
            ->where('status', Campaign::RECRUITING)
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('tester_id', $user->id))
            ->withCount(['enrollments as taken' => fn ($q) => $q->where('status', Enrollment::ACTIVE)])
            ->with('androidApp')
            ->oldest('paid_at')
            ->limit(12)
            ->get()
            ->filter(fn (Campaign $c) => $c->taken < $c->slots);

        return view('tester.dashboard', [
            'user' => $user,
            'wallet' => $wallets->summary($user),
            'terms' => $terms,
            'missAllowed' => $terms->missAllowed,
            'enrollments' => $enrollments,
            'available' => $available,
            'history' => $wallets->history($user)->take(20),
            'pendingWithdrawal' => $user->withdrawals()->whereIn('status', [Withdrawal::PENDING, Withdrawal::PROCESSING])->latest('id')->first(),
            'completed' => $user->enrollments()->where('status', Enrollment::COMPLETED)->count(),
            'appUrl' => config('mihak.tester_app_url'),
            'deviceLinked' => $user->device_id !== null,
        ]);
    }

    public function withdraw(Request $request, WithdrawalService $service): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(array_keys(Withdrawal::METHODS))],
            'account' => ['required', 'string', 'max:40'],
        ], [
            'amount.required' => 'اكتب المبلغ.',
            'account.required' => 'اكتب رقم المحفظة أو البطاقة.',
        ]);

        $service->request($request->user(), (int) $data['amount'], $data['method'], $data['account']);

        return back()->with('success', 'أُرسل طلب السحب. تراجعه الإدارة ثم يُحوَّل المبلغ.');
    }
}
