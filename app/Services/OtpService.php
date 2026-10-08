<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Sms\SmsSender;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\Hash;

/**
 * رمز التحقق برسالة نصية. يُشغَّل ويُطفأ من لوحة الإدارة (otp_enabled).
 * وهو موقوف، يُعدّ كل رقم مؤكَّداً تلقائياً ولا تُرسل أي رسالة.
 */
class OtpService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly SmsSender $sms,
        private readonly SettingsService $settings,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->bool('otp_enabled');
    }

    public function resendSeconds(): int
    {
        return max(0, $this->settings->int('otp_resend_seconds'));
    }

    /** إذا كان الرمز موقوفاً يؤكد الرقم فوراً. يعيد true إذا أصبح المستخدم مؤكَّداً. */
    public function autoVerifyIfDisabled(User $user): bool
    {
        if ($user->isVerified()) {
            return true;
        }
        if ($this->enabled()) {
            return false;
        }
        $user->update(['phone_verified_at' => now()]);

        return true;
    }

    /** الثواني المتبقية قبل السماح بإرسال رمز جديد لهذا الرقم */
    public function secondsUntilResend(string $phone): int
    {
        $last = OtpCode::query()->where('phone', $phone)->latest('id')->first();
        if (! $last) {
            return 0;
        }
        $elapsed = (int) $last->created_at->diffInSeconds(now(), true);

        return max(0, $this->resendSeconds() - $elapsed);
    }

    public function send(string $phone): void
    {
        if (! $this->enabled()) {
            return;
        }

        $wait = $this->secondsUntilResend($phone);
        if ($wait > 0) {
            throw new RuleViolation("انتظر {$wait} ثانية قبل طلب رمز جديد.");
        }

        $maxPerHour = max(1, $this->settings->int('otp_max_per_hour'));
        $recent = OtpCode::query()->where('phone', $phone)->where('created_at', '>', now()->subHour())->count();
        if ($recent >= $maxPerHour) {
            throw new RuleViolation('طلبت رموزاً كثيرة. حاول بعد ساعة.');
        }

        $ttl = max(1, $this->settings->int('otp_ttl_minutes'));
        $code = (string) random_int(100000, 999999);

        // أي رمز سابق لم يُستخدم يصبح غير صالح
        OtpCode::query()->where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        OtpCode::query()->create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->sms->send($phone, "رمز التحقق في مِحَكّ: {$code}\nصالح لمدة {$ttl} دقائق.");
    }

    public function verify(string $phone, string $code): bool
    {
        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $otp->increment('attempts');
        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->update(['consumed_at' => now(), 'verified_at' => now()]);

        return true;
    }

    /** @return array{today:int, week:int, month:int, total:int, verified_month:int} */
    public function stats(): array
    {
        $count = fn ($from) => OtpCode::query()->where('created_at', '>=', $from)->count();

        return [
            'today' => $count(now()->startOfDay()),
            'week' => $count(now()->subDays(7)),
            'month' => $count(now()->startOfMonth()),
            'total' => OtpCode::query()->count(),
            'verified_month' => OtpCode::query()->where('created_at', '>=', now()->startOfMonth())->whereNotNull('verified_at')->count(),
        ];
    }
}
