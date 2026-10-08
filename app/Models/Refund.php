<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const LABELS = [self::PENDING => 'قيد التنفيذ', self::PAID => 'أُعيد المبلغ', self::FAILED => 'فشل الاسترداد'];

    protected $fillable = ['campaign_id', 'user_id', 'payment_id', 'amount', 'reason', 'status', 'gateway_ref', 'admin_id', 'admin_note', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }
}
