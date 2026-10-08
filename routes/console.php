<?php

use Illuminate\Support\Facades\Schedule;

// على السيرفر أضف سطراً واحداً إلى crontab:
// * * * * * cd /path/to/mihak-server && php artisan schedule:run >> /dev/null 2>&1

Schedule::command('mihak:close-day')
    ->dailyAt(config('mihak.close_day_at', '03:00'))
    ->timezone(config('mihak.timezone', 'Asia/Baghdad'))
    ->withoutOverlapping()
    ->onOneServer();

// إعادة تشغيل كل ساعة تلتقط أي يوم فات بسبب توقف السيرفر (العملية آمنة للتكرار)
Schedule::command('mihak:close-day')->hourlyAt(17)->withoutOverlapping();

Schedule::command('mihak:maintain')->everyTenMinutes()->withoutOverlapping();

Schedule::command('mihak:ledger-check')->dailyAt('04:00')->timezone(config('mihak.timezone', 'Asia/Baghdad'));
