<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ledger_transaction_id', 'account', 'holder_type', 'holder_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }
}
