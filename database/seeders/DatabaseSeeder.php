<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** ينشئ حساب الإدارة الأول من قيم ADMIN_* في ملف .env */
    public function run(): void
    {
        $admin = config('mihak.admin');
        if (empty($admin['phone']) || empty($admin['password'])) {
            $this->command?->warn('لم يُنشأ حساب إدارة: املأ ADMIN_PHONE و ADMIN_PASSWORD في .env');

            return;
        }

        User::query()->updateOrCreate(
            ['phone' => $admin['phone']],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'role' => User::ADMIN,
                'phone_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $this->command?->info("حساب الإدارة: {$admin['phone']}");
    }
}
