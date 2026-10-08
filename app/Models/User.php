<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public const DEVELOPER = 'developer';

    public const TESTER = 'tester';

    public const ADMIN = 'admin';

    protected $fillable = [
        'name', 'phone', 'email', 'role', 'password', 'phone_verified_at', 'device_id', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function apps(): HasMany
    {
        return $this->hasMany(AndroidApp::class, 'developer_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'tester_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }

    public function isTester(): bool
    {
        return $this->role === self::TESTER;
    }

    public function isDeveloper(): bool
    {
        return $this->role === self::DEVELOPER;
    }

    public function isVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /** اسم صفحة البداية في الموقع حسب الدور */
    public function homeRoute(): string
    {
        return match ($this->role) {
            self::ADMIN => 'admin.dashboard',
            self::TESTER => 'tester.dashboard',
            default => 'dashboard',
        };
    }
}
