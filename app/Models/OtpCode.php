<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = ['phone', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'verified_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /** @return array{0:string, 1:string} [النص، صنف اللون] */
    public function state(): array
    {
        return match (true) {
            $this->verified_at !== null => ['استُخدم', 'good'],
            $this->consumed_at !== null => ['استُبدل برمز أحدث', 'pending'],
            $this->expires_at->isPast() => ['انتهت صلاحيته', 'rejected'],
            default => ['بانتظار الإدخال', 'testing'],
        };
    }
}
