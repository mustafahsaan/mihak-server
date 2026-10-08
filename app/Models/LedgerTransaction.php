<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'description', 'reference_type', 'reference_id'];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
