<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    public const ACTIVE = 'active';

    public const REMOVED = 'removed';

    public const COMPLETED = 'completed';

    public const VOIDED = 'voided'; // الحملة فشلت فلم يُدفع للمقعد

    public const REASONS = [
        'uninstalled' => 'ألغى تثبيت التطبيق',
        'too_many_misses' => 'تجاوز أيام الغياب المسموحة',
        'left' => 'انسحب من الاختبار',
        'campaign_failed' => 'لم يكتمل عدد المختبِرين',
    ];

    protected $fillable = [
        'campaign_id', 'tester_id', 'status', 'accrued', 'verified_days', 'misses',
        'joined_at', 'installed_confirmed_at', 'ended_at', 'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'installed_confirmed_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(DailyCheck::class);
    }

    public function reasonLabel(): ?string
    {
        return $this->end_reason ? (self::REASONS[$this->end_reason] ?? $this->end_reason) : null;
    }
}
