@extends('layouts.app')
@section('title', 'رمز التحقق')

@section('content')
<div class="grid-2">
    <form class="panel" method="POST" action="{{ route('admin.otp.update') }}">
        @csrf
        @method('PUT')
        <div class="card-top">
            <h1>رمز التحقق (OTP)</h1>
            <span class="chip {{ $values['otp_enabled'] ? 'good' : 'pending' }}">{{ $values['otp_enabled'] ? 'مفعّل' : 'موقوف' }}</span>
        </div>
        <p class="muted small">وهو موقوف، يُنشأ الحساب ويُفعَّل مباشرة بدون رسالة نصية، في الموقع وفي تطبيق المختبِرين.</p>

        <label class="check">
            <input type="checkbox" name="otp_enabled" value="1" @checked($values['otp_enabled'])>
            <span><b>تفعيل إرسال رمز التحقق</b><br><span class="muted small">كل تسجيل جديد يحتاج إدخال رمز يصل برسالة نصية</span></span>
        </label>

        <div class="form-grid">
            <label class="f" for="otp_resend_seconds">عداد إعادة الإرسال (ثانية)
                <small>المدة قبل أن يُسمح بطلب رمز جديد</small>
                <input id="otp_resend_seconds" name="otp_resend_seconds" type="number" inputmode="numeric" min="0" max="3600" value="{{ old('otp_resend_seconds', $values['otp_resend_seconds']) }}" required>
            </label>
            <label class="f" for="otp_ttl_minutes">صلاحية الرمز (دقيقة)
                <small>بعدها يحتاج المستخدم رمزاً جديداً</small>
                <input id="otp_ttl_minutes" name="otp_ttl_minutes" type="number" inputmode="numeric" min="1" max="120" value="{{ old('otp_ttl_minutes', $values['otp_ttl_minutes']) }}" required>
            </label>
            <label class="f" for="otp_max_per_hour">أقصى رموز للرقم الواحد بالساعة
                <small>يحميك من استنزاف رصيد الرسائل</small>
                <input id="otp_max_per_hour" name="otp_max_per_hour" type="number" inputmode="numeric" min="1" max="50" value="{{ old('otp_max_per_hour', $values['otp_max_per_hour']) }}" required>
            </label>
        </div>
        @if ($errors->any())
            <div class="callout bad small">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif

        @if ($smsDriver === 'log')
            <div class="callout warn small">مزوّد الرسائل الحالي هو <code>log</code>: الرموز تُكتب في <code>storage/logs/laravel.log</code> ولا تصل للهواتف. اربط مزوّد SMS حقيقياً قبل التفعيل على السيرفر.</div>
        @endif

        <div><button class="btn" type="submit">حفظ</button></div>
    </form>

    <aside class="stack">
        <div class="grid-2" style="gap:10px">
            <div class="kpi"><span class="l">رسائل اليوم</span><span class="v num">{{ number_format($stats['today']) }}</span><span class="s">&nbsp;</span></div>
            <div class="kpi"><span class="l">آخر 7 أيام</span><span class="v num">{{ number_format($stats['week']) }}</span><span class="s">&nbsp;</span></div>
            <div class="kpi"><span class="l">هذا الشهر</span><span class="v num">{{ number_format($stats['month']) }}</span><span class="s">استُخدم منها {{ number_format($stats['verified_month']) }}</span></div>
            <div class="kpi"><span class="l">المجموع</span><span class="v num">{{ number_format($stats['total']) }}</span><span class="s">منذ البداية</span></div>
        </div>
        <p class="muted small">كل رمز يُرسل يُحسب رسالة واحدة، فهذه الأرقام تساعدك على تقدير كلفة الرسائل.</p>
    </aside>
</div>

<div class="head"><h2>آخر الرموز المرسلة</h2><span class="muted small">الرمز نفسه لا يُحفظ، فقط بصمته المشفّرة</span></div>
<div class="tbl">
    <table>
        <thead><tr><th>الرقم</th><th>أُرسل</th><th>ينتهي</th><th>محاولات الإدخال</th><th>الحالة</th></tr></thead>
        <tbody>
        @forelse ($codes as $code)
            @php
                [$label, $cls] = $code->state();
            @endphp
            <tr>
                <td class="num" dir="ltr">{{ $code->phone }}</td>
                <td class="num">{{ $code->created_at?->format('Y-m-d H:i') }}</td>
                <td class="num">{{ $code->expires_at?->format('H:i') }}</td>
                <td class="num">{{ $code->attempts }}</td>
                <td><span class="chip {{ $cls }}">{{ $label }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">لم يُرسل أي رمز بعد.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('admin._pager', ['paginator' => $codes])
@endsection
