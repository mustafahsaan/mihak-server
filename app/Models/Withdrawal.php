<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing'; // أُرسل لزين كاش وننتظر النتيجة

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const FAILED = 'failed';

    public const LABELS = [
        self::PENDING => 'قيد المراجعة',
        self::PROCESSING => 'قيد التحويل',
        self::PAID => 'حُوّل',
        self::REJECTED => 'مرفوض',
        self::FAILED => 'فشل التحويل',
    ];

    public const METHODS = ['zaincash' => 'زين كاش', 'qicard' => 'كي كارد'];

    protected $fillable = ['user_id', 'amount', 'method', 'account', 'status', 'gateway_ref', 'admin_id', 'admin_note', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
