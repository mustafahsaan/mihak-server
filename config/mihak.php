<?php

return [
    // القيم الافتراضية للإعدادات. لوحة الإدارة تحفظ أي تعديل في جدول settings
    // ويُطبَّق على الحملات التي تُنشأ بعد الحفظ فقط.
    'defaults' => [
        'price' => 20000,                    // د.ع لكل تطبيق
        'commission_percent' => 20,          // عمولة المنصة
        'required_testers' => 12,            // شرط Google
        'reserve_testers' => 2,              // مقاعد احتياط
        'test_days' => 16,                   // 14 يوماً لشرط Google + يومان احتياط
        'miss_allowed' => 2,                 // أيام الغياب المسموحة، الغياب التالي يُخرج المختبِر
        'min_open_seconds' => 30,            // أقل مدة استخدام يومية تُحسب «فتحاً»
        'withdraw_min' => 10000,             // لا سحب قبل هذا الرصيد
        'failure_pays_completers' => false,  // عند فشل الحملة: هل يُدفع لمن أكمل؟
        'refund_includes_commission' => true, // عند الاسترداد: هل تُعاد العمولة للمطوّر؟
        'seat_confirm_hours' => 24,          // مهلة تأكيد التثبيت بعد الانضمام

        // رمز التحقق (OTP) — يُدار من لوحة الإدارة ← الإعدادات
        'otp_enabled' => false,              // موقوف: التسجيل يتم بدون رمز حتى تفعّله
        'otp_resend_seconds' => 60,          // العداد قبل السماح بإعادة الإرسال
        'otp_ttl_minutes' => 10,             // صلاحية الرمز
        'otp_max_per_hour' => 5,             // أقصى عدد رموز لنفس الرقم خلال ساعة

        'site_font' => 'Cairo',              // خط الموقع، يُختار من لوحة الإدارة ← الإعدادات
    ],

    // الخطوط المتاحة للموقع (كلها من Google Fonts وتدعم العربية)
    'fonts' => [
        'Cairo' => ['label' => 'Cairo', 'google' => 'Cairo:wght@400;500;600;700'],
        'Tajawal' => ['label' => 'Tajawal', 'google' => 'Tajawal:wght@400;500;700'],
        'Almarai' => ['label' => 'Almarai', 'google' => 'Almarai:wght@400;700'],
        'IBM Plex Sans Arabic' => ['label' => 'IBM Plex Sans Arabic', 'google' => 'IBM+Plex+Sans+Arabic:wght@400;500;600;700'],
        'Noto Kufi Arabic' => ['label' => 'Noto Kufi Arabic', 'google' => 'Noto+Kufi+Arabic:wght@400;500;600;700'],
        'Readex Pro' => ['label' => 'Readex Pro', 'google' => 'Readex+Pro:wght@400;500;600;700'],
    ],

    'google_days' => 14,
    'google_testers' => 12,
    'group_email' => env('MIHAK_GROUP_EMAIL', 'mihak-testers@googlegroups.com'),
    'tester_app_url' => env('MIHAK_TESTER_APP_URL'),
    'fake_payments' => (bool) env('MIHAK_FAKE_PAYMENTS', false),
    'auto_zaincash_payouts' => (bool) env('MIHAK_AUTO_ZAINCASH_PAYOUTS', false),

    // يوم الاختبار يُغلق بعد منتصف الليل بثلاث ساعات، حتى يصل تقرير آخر الليل من الهواتف
    'timezone' => 'Asia/Baghdad',
    'close_day_at' => '03:00',

    'admin' => [
        'name' => env('ADMIN_NAME', 'مدير المنصة'),
        'phone' => env('ADMIN_PHONE'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
