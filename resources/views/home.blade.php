@extends('layouts.app')

@section('content')
<section class="grid-2" style="align-items:end;margin-bottom:20px">
    <div class="stack">
        <span class="eyebrow">للمطوّرين في العراق</span>
        <h1>انشر تطبيقك على Google Play بعد اختبار مغلق حقيقي</h1>
        <p class="muted">حسابات المطوّرين الشخصية الجديدة تحتاج {{ config('mihak.google_testers') }} مختبِراً مشتركين {{ config('mihak.google_days') }} يوماً متواصلة قبل النشر. نوفّر لك مختبِرين حقيقيين يفتحون تطبيقك كل يوم، ويبقى مبلغك في حساب الضمان حتى ينتهي الاختبار.</p>
        <div class="row">
            <a class="btn" href="{{ route('register') }}">ابدأ كمطوّر</a>
            <a class="btn ghost" href="{{ route('login') }}">لدي حساب</a>
        </div>
    </div>
    <div class="panel price">
        <span class="muted small">سعر التطبيق الواحد</span>
        <b class="num">@iqd($terms->price)</b>
        <ul class="ticks">
            <li>{{ $terms->slots }} مختبِراً ({{ $terms->required }} + {{ $terms->slots - $terms->required }} احتياط)</li>
            <li>{{ $terms->days }} يوماً ({{ config('mihak.google_days') }} + {{ max(0, $terms->days - config('mihak.google_days')) }} احتياط)</li>
            <li>الدفع بزين كاش أو كي كارد</li>
            <li>استرداد كامل أو إعادة مجانية إذا لم يكتمل العدد</li>
        </ul>
    </div>
</section>

<section class="grid-3">
    <div class="panel">
        <h3>المبلغ في الضمان</h3>
        <p class="muted small">لا يُصرف أي دينار للمختبِرين قبل انتهاء الاختبار، وكل حركة مالية مسجّلة في دفتر لا يُعدَّل.</p>
    </div>
    <div class="panel">
        <h3>تحقق يومي تلقائي</h3>
        <p class="muted small">تطبيق المختبِر يتحقق كل يوم أن تطبيقك مثبّت ومفتوح. من يلغي التثبيت أو يغيب أكثر من {{ $terms->missAllowed }} أيام يخرج ويخسر مبلغه.</p>
    </div>
    <div class="panel">
        <h3>ملاحظات حقيقية</h3>
        <p class="muted small">يرسل المختبِرون ملاحظاتهم عن تطبيقك وتراها في صفحة الاختبار، لتصلحها قبل النشر.</p>
    </div>
</section>

<section class="panel soft" style="margin-top:20px">
    <span class="eyebrow">للمختبِرين</span>
    <h2>اربح {{ number_format($terms->share) }} د.ع عن كل اختبار تكمله</h2>
    <p class="small">ثبّت تطبيق مِحَكّ على هاتف أندرويد، انضم للاختبارات، وافتح التطبيقات يومياً. يتحول المبلغ إلى رصيدك بعد {{ $terms->days }} يوماً، ويُسحب إلى زين كاش أو كي كارد عند وصوله {{ number_format($settings['withdraw_min']) }} د.ع.</p>
    <div class="row">
        <a class="btn money" href="{{ route('tester.register') }}">سجّل كمختبِر</a>
        @if (config('mihak.tester_app_url'))
            <a class="btn ghost" href="{{ config('mihak.tester_app_url') }}">حمّل تطبيق المختبِرين</a>
        @endif
    </div>
</section>
@endsection
