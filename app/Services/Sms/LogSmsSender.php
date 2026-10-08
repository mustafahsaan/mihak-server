<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * للتطوير: يكتب الرسالة في storage/logs/laravel.log بدل إرسالها.
 * للإنتاج: أنشئ كلاساً يطبّق SmsSender لمزوّد الرسائل الذي تختاره
 * (مثل خدمة SMS عراقية أو واتساب للأعمال) وسجّله في AppServiceProvider.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS to {$phone}: {$message}");
    }
}
