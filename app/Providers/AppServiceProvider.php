<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Withdrawal;
use App\Payments\PaymentManager;
use App\Services\LedgerService;
use App\Services\SettingsService;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(LedgerService::class);
        $this->app->singleton(PaymentManager::class);

        $this->app->bind(SmsSender::class, fn () => match (config('services.sms.driver')) {
            // أضف هنا مزوّد الرسائل الحقيقي، مثلاً: 'otpiq' => new OtpIqSmsSender(...)
            default => new LogSmsSender,
        });
    }

    public function boot(): void
    {
        // أثناء التطوير: خطأ واضح إذا حاول الكود حفظ حقل غير مسموح بدل تجاهله بصمت
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // أسماء قصيرة ثابتة لأصحاب الحسابات في دفتر القيود
        Relation::enforceMorphMap([
            'user' => User::class,
            'campaign' => Campaign::class,
            'payment' => Payment::class,
            'withdrawal' => Withdrawal::class,
            'refund' => Refund::class,
            'enrollment' => Enrollment::class,
        ]);

        // @iqd($amount) يطبع المبلغ بالشكل: 20,000 د.ع
        Blade::directive('iqd', fn (string $expression) => "<?php echo e(\\App\\Support\\Dates::money({$expression})); ?>");
        // @arDate($date) يطبع التاريخ بالشكل: 8 تشرين الأول
        Blade::directive('arDate', fn (string $expression) => "<?php echo e(\\App\\Support\\Dates::arabic({$expression})); ?>");

        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(3)->by($request->input('phone').'|'.$request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
