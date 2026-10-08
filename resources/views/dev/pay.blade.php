@extends('layouts.app')
@section('title', 'الدفع')

@section('content')
<div class="grid-2">
    <form class="panel" method="POST" action="{{ route('campaigns.pay.start', $campaign) }}">
        @csrf
        <span class="eyebrow">الدفع</span>
        <h1>{{ $app->name }}</h1>
        <code>{{ $app->package_name }}</code>
        <div class="price"><span class="muted small">المبلغ</span><b class="num">@iqd($terms->price)</b></div>
        <p class="muted small">يُحجز المبلغ في حساب الضمان، ولا يُصرف للمختبِرين إلا بعد اكتمال {{ $terms->days }} يوماً.</p>

        <fieldset style="border:0;padding:0;margin:0;display:grid;gap:10px">
            <legend class="small" style="margin-bottom:8px;font-weight:600">اختر وسيلة الدفع</legend>
            @foreach ($gateways as $key => $label)
                <label class="check panel" style="padding:12px 14px">
                    <input type="radio" name="gateway" value="{{ $key }}" @checked($loop->first) required>
                    <span><b>{{ $label }}</b><br><span class="muted small">{{ $key === 'zaincash' ? 'تُحوَّل إلى صفحة زين كاش وتدفع من محفظتك' : 'تُحوَّل إلى صفحة كي كارد وتدفع ببطاقتك أو بسوبر كي' }}</span></span>
                </label>
            @endforeach
        </fieldset>
        @error('gateway')<p class="err">{{ $message }}</p>@enderror
        @if ($fake)<p class="callout warn small">وضع التجربة مفعّل: ستظهر صفحة دفع تجريبية بدل البوابة الحقيقية.</p>@endif
        <div class="row">
            <button class="btn money" type="submit">ادفع @iqd($terms->price)</button>
            <a class="btn ghost" href="{{ route('dashboard') }}">لاحقاً</a>
        </div>
    </form>

    <aside class="panel soft">
        <h2>بعد الدفع</h2>
        <ol class="steps">
            <li><span>افتح Play Console ← الاختبار ← الاختبار المغلق.</span></li>
            <li><span>أضف المجموعة <code>{{ config('mihak.group_email') }}</code> إلى قائمة المختبِرين.</span></li>
            <li><span>تأكد أن إصدار الاختبار منشور ووافقت عليه Google.</span></li>
            <li><span>بعد اكتمال {{ $terms->slots }} مقعداً يبدأ العد من اليوم التالي، وبعد اليوم {{ config('mihak.google_days') }} قدّم طلب الوصول إلى الإنتاج.</span></li>
        </ol>
    </aside>
</div>
@endsection
