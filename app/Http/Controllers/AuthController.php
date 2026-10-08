<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OtpService;
use App\Services\SettingsService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** دخول وتسجيل المطوّرين والإدارة عبر الموقع. المختبِرون يستخدمون تطبيق الهاتف. */
class AuthController extends Controller
{
    public function home(SettingsService $settings): View
    {
        return view('home', ['terms' => $settings->terms(), 'settings' => $settings->all()]);
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $phone = Phone::normalize($data['phone']);

        if (! $phone || ! Auth::attempt(['phone' => $phone, 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['phone' => 'رقم الهاتف أو كلمة المرور غير صحيحة.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route($request->user()->homeRoute()));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, OtpService $otp): RedirectResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'regex:/^07\d{9}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone.regex' => 'اكتب رقماً عراقياً من 11 رقماً يبدأ بـ 07.',
            'phone.unique' => 'هذا الرقم مسجّل مسبقاً. سجّل الدخول.',
            'password.min' => 'كلمة المرور 8 أحرف على الأقل.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => User::DEVELOPER,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // رمز التحقق موقوف من الإدارة: الحساب يُفعَّل مباشرة
        if ($otp->autoVerifyIfDisabled($user)) {
            return redirect()->route('dashboard')->with('success', 'أُنشئ حسابك. أضف أول تطبيق للاختبار.');
        }

        $otp->send($user->phone);

        return redirect()->route('otp.show');
    }

    public function showOtp(Request $request, OtpService $otp): View|RedirectResponse
    {
        $user = $request->user();
        if ($otp->autoVerifyIfDisabled($user)) {
            return redirect()->route($user->homeRoute());
        }

        return view('auth.otp', [
            'phone' => $user->phone,
            'resendAfter' => $otp->secondsUntilResend($user->phone),
        ]);
    }

    public function verifyOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'الرمز 6 أرقام.']);
        $user = $request->user();

        if (! $otp->verify($user->phone, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'الرمز غير صحيح أو انتهت صلاحيته.']);
        }

        $user->update(['phone_verified_at' => now()]);

        return redirect()->route($user->homeRoute())->with('success', 'تم تأكيد رقم هاتفك.');
    }

    public function resendOtp(Request $request, OtpService $otp): RedirectResponse
    {
        if ($otp->autoVerifyIfDisabled($request->user())) {
            return redirect()->route($request->user()->homeRoute());
        }
        $otp->send($request->user()->phone);

        return back()->with('success', 'أرسلنا رمزاً جديداً.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
