<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** تسجيل ودخول المختبِرين من تطبيق الهاتف */
class AuthController extends Controller
{
    public function register(Request $request, OtpService $otp): JsonResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'regex:/^07\d{9}$/', 'unique:users,phone'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'device_id' => ['required', 'string', 'min:8', 'max:100', 'unique:users,device_id'],
        ], [
            'phone.regex' => 'اكتب رقماً عراقياً من 11 رقماً يبدأ بـ 07.',
            'phone.unique' => 'هذا الرقم مسجّل مسبقاً. سجّل الدخول.',
            'email.unique' => 'حساب Gmail هذا مستخدم في حساب آخر.',
            'email.email' => 'اكتب بريد Gmail صحيحاً.',
            'password.min' => 'كلمة المرور 8 أحرف على الأقل.',
            'device_id.unique' => 'هذا الجهاز مسجّل بحساب آخر. يُسمح بحساب واحد لكل جهاز.',
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'device_id' => $data['device_id'],
            'role' => User::TESTER,
        ]);

        // رمز التحقق موقوف من الإدارة: نعيد رمز الدخول مباشرة
        if ($otp->autoVerifyIfDisabled($user)) {
            return $this->tokenResponse($user, 201);
        }

        $otp->send($user->phone);

        return response()->json([
            'message' => 'أرسلنا رمز التحقق إلى '.$user->phone,
            'phone' => $user->phone,
            'otp_required' => true,
            'resend_after' => $otp->resendSeconds(),
        ], 201);
    }

    public function verify(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
            'device_id' => ['required', 'string'],
        ]);
        $user = $this->tester(Phone::normalize($data['phone']));

        if (! $user || ! $otp->verify($user->phone, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'الرمز غير صحيح أو انتهت صلاحيته.']);
        }
        $this->assertDevice($user, $data['device_id']);

        $user->update(['phone_verified_at' => $user->phone_verified_at ?? now()]);

        return $this->tokenResponse($user);
    }

    public function login(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string'],
        ]);
        $user = $this->tester(Phone::normalize($data['phone']));

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages(['phone' => 'رقم الهاتف أو كلمة المرور غير صحيحة.']);
        }
        $this->assertDevice($user, $data['device_id']);

        if (! $otp->autoVerifyIfDisabled($user)) {
            // لا نرسل رمزاً جديداً إذا لم ينتهِ عداد إعادة الإرسال؛ الرمز السابق ما زال صالحاً
            if ($otp->secondsUntilResend($user->phone) === 0) {
                $otp->send($user->phone);
            }

            return response()->json([
                'message' => 'أكّد رقم هاتفك بالرمز الذي أرسلناه.',
                'needs_verification' => true,
                'phone' => $user->phone,
                'resend_after' => $otp->secondsUntilResend($user->phone),
            ], 403);
        }

        return $this->tokenResponse($user);
    }

    public function resend(Request $request, OtpService $otp): JsonResponse
    {
        $user = $this->tester(Phone::normalize((string) $request->input('phone')));
        if ($user && ! $user->isVerified() && $otp->enabled()) {
            $otp->send($user->phone); // يرفض برسالة واضحة إذا لم ينتهِ العداد
        }

        return response()->json([
            'message' => 'إذا كان الرقم مسجّلاً فسيصله رمز جديد.',
            'resend_after' => $user ? $otp->secondsUntilResend($user->phone) : $otp->resendSeconds(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    private function tester(?string $phone): ?User
    {
        return $phone ? User::query()->where('phone', $phone)->where('role', User::TESTER)->first() : null;
    }

    private function assertDevice(User $user, string $deviceId): void
    {
        if ($user->device_id === null) {
            if (User::query()->where('device_id', $deviceId)->exists()) {
                throw ValidationException::withMessages(['device_id' => 'هذا الجهاز مسجّل بحساب آخر.']);
            }
            $user->update(['device_id' => $deviceId]);

            return;
        }
        if (! hash_equals($user->device_id, $deviceId)) {
            throw ValidationException::withMessages(['phone' => 'هذا الحساب مسجّل على جهاز آخر. تواصل مع الدعم لنقله.']);
        }
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $user->tokens()->delete(); // جلسة واحدة فعّالة لكل مختبِر

        return response()->json([
            'token' => $user->createToken('android')->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone, 'email' => $user->email],
            'otp_required' => false,
        ], $status);
    }
}
