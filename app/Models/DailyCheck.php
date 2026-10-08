<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCheck extends Model
{
    protected $fillable = ['enrollment_id', 'day_number', 'check_date', 'installed', 'foreground_seconds', 'reports', 'reported_at'];

    protected function casts(): array
    {
        return [
            'check_date' => 'immutable_date',
            'installed' => 'boolean',
            'reported_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
