<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Services\OtpService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** تشغيل رمز التحقق وإيقافه، عداد إعادة الإرسال، وإحصاءات الرسائل المرسلة */
class OtpController extends Controller
{
    public function index(SettingsService $settings, OtpService $otp): View
    {
        return view('admin.otp', [
            'values' => $settings->all(),
            'stats' => $otp->stats(),
            'codes' => OtpCode::query()->latest('id')->paginate(40),
            'smsDriver' => config('services.sms.driver'),
        ]);
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'otp_resend_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'otp_ttl_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'otp_max_per_hour' => ['required', 'integer', 'min:1', 'max:50'],
        ], [
            'otp_resend_seconds.max' => 'العداد لا يزيد عن ساعة (3600 ثانية).',
        ]);
        $data['otp_enabled'] = $request->boolean('otp_enabled');

        $settings->put($data);

        return back()->with('success', $data['otp_enabled']
            ? 'رمز التحقق مفعّل: كل تسجيل جديد يحتاج رمزاً برسالة نصية.'
            : 'رمز التحقق موقوف: التسجيل يتم مباشرة بدون رسالة.');
    }
}
