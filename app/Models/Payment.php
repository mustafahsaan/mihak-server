<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const MISMATCH = 'mismatch'; // البوابة أكدت مبلغاً مختلفاً، يحتاج مراجعة يدوية

    public const GATEWAYS = ['zaincash' => 'زين كاش', 'qicard' => 'كي كارد'];

    protected $fillable = ['campaign_id', 'user_id', 'gateway', 'amount', 'status', 'external_ref', 'gateway_txn_id', 'payload', 'paid_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gatewayLabel(): string
    {
        return self::GATEWAYS[$this->gateway] ?? $this->gateway;
    }

    /** يضيف معلومات إلى payload دون مسح ما سبق */
    public function remember(array $data): void
    {
        $this->payload = array_merge($this->payload ?? [], $data);
        $this->save();
    }
}
