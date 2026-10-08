<?php

namespace App\Models;

use App\Domain\Terms;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    public const UNPAID = 'unpaid';

    public const RECRUITING = 'recruiting';

    public const TESTING = 'testing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';     // بانتظار قرار المطوّر

    public const REFUNDED = 'refunded';

    public const RERUN = 'rerun';       // استُبدلت بدورة جديدة

    public const LABELS = [
        self::UNPAID => 'بانتظار الدفع',
        self::RECRUITING => 'جمع المختبِرين',
        self::TESTING => 'الاختبار جارٍ',
        self::COMPLETED => 'اكتمل',
        self::FAILED => 'لم يكتمل العدد',
        self::REFUNDED => 'استُرد المبلغ',
        self::RERUN => 'أُعيد الاختبار',
    ];

    protected $fillable = [
        'android_app_id', 'parent_campaign_id', 'cycle', 'status',
        'price', 'commission_percent', 'share', 'slots', 'required', 'days', 'miss_allowed',
        'paid_at', 'started_on', 'days_done', 'last_closed_on', 'completers', 'failure_choice', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'ended_at' => 'datetime',
            'started_on' => 'immutable_date',
            'last_closed_on' => 'immutable_date',
        ];
    }

    public function androidApp(): BelongsTo
    {
        return $this->belongsTo(AndroidApp::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_campaign_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function terms(): Terms
    {
        return new Terms($this->price, $this->commission_percent, $this->share, $this->slots, $this->required, $this->days, $this->miss_allowed);
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function endDate(): ?CarbonImmutable
    {
        return $this->started_on?->addDays($this->days - 1);
    }

    /** رقم يوم الاختبار لتاريخ معيّن، أو null إذا كان التاريخ خارج المدة */
    public function dayNumberFor(CarbonInterface $date): ?int
    {
        if ($this->started_on === null) {
            return null;
        }
        $n = (int) round($this->started_on->startOfDay()->diffInDays(CarbonImmutable::parse($date)->startOfDay(), false)) + 1;

        return ($n >= 1 && $n <= $this->days) ? $n : null;
    }

    public function activeCount(): int
    {
        return $this->enrollments()->where('status', Enrollment::ACTIVE)->count();
    }

    /** أول دفعة ناجحة لهذه الحملة أو للحملة الأصلية في حال كانت إعادة اختبار */
    public function originPayment(): ?Payment
    {
        $campaign = $this;
        while ($campaign) {
            $payment = $campaign->payments()->where('status', Payment::PAID)->oldest('paid_at')->first();
            if ($payment) {
                return $payment;
            }
            $campaign = $campaign->parent;
        }

        return null;
    }
}
