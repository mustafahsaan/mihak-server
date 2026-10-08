<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'اختبار مغلق حقيقي') · مِحَكّ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php
        // الخط يُختار من لوحة الإدارة ← الإعدادات
        $siteFont = app(\App\Services\SettingsService::class)->font();
    @endphp
    <link rel="stylesheet" href="{{ $siteFont['url'] }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="{{ asset('css/mihak.css') }}">
    <style>
        :root {
            --font-display: "{{ $siteFont['name'] }}", Tahoma, Arial, sans-serif;
            --font-body: "{{ $siteFont['name'] }}", Tahoma, Arial, sans-serif;
        }
    </style>
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}">
            <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true"><rect x="1" y="1" width="32" height="32" rx="9" style="fill:var(--ink)"/><rect x="6" y="15" width="22" height="5" rx="2.5" transform="rotate(-24 17 17.5)" style="fill:var(--money-bright)"/></svg>
            <div><strong>مِحَكّ</strong><small>اختبار مغلق حقيقي لـ Google Play</small></div>
        </a>
        <nav class="nav" aria-label="القائمة">
            @auth
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" @class(['on' => request()->routeIs('admin.dashboard')])>الرئيسية</a>
                    <a href="{{ route('admin.withdrawals') }}" @class(['on' => request()->routeIs('admin.withdrawals')])>السحوبات</a>
                    <a href="{{ route('admin.refunds') }}" @class(['on' => request()->routeIs('admin.refunds')])>الاستردادات</a>
                    <a href="{{ route('admin.campaigns') }}" @class(['on' => request()->routeIs('admin.campaigns*')])>الحملات</a>
                    <a href="{{ route('admin.ledger') }}" @class(['on' => request()->routeIs('admin.ledger')])>دفتر الحركات</a>
                    <a href="{{ route('admin.settings') }}" @class(['on' => request()->routeIs('admin.settings')])>الإعدادات</a>
                    <a href="{{ route('admin.otp') }}" @class(['on' => request()->routeIs('admin.otp')])>رمز التحقق</a>
                @elseif (auth()->user()->isTester())
                    <a href="{{ route('tester.dashboard') }}" @class(['on' => request()->routeIs('tester.dashboard')])>حسابي</a>
                @else
                    <a href="{{ route('dashboard') }}" @class(['on' => request()->routeIs('dashboard')])>تطبيقاتي</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">خروج</button></form>
            @else
                <a href="{{ route('login') }}">دخول</a>
                <a href="{{ route('tester.register') }}">سجّل كمختبِر</a>
                <a class="btn sm" href="{{ route('register') }}">حساب مطوّر</a>
            @endauth
        </nav>
    </header>

    @if (session('success'))
        <div class="flash success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="flash error" role="alert">{{ session('error') }}</div>
    @endif

    @yield('content')
</div>
</body>
</html>
