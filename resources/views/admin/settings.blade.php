@extends('layouts.app')
@section('title', 'الإعدادات')

@section('content')
<div class="grid-2">
    <form class="panel" method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')
        <h1>الإعدادات</h1>
        <p class="muted small">تُطبّق على التطبيقات التي تُدفع بعد الحفظ. الحملات القائمة تحتفظ بشروطها.</p>

        @php
            $fields = [
                'price' => ['سعر التطبيق (د.ع)', ''],
                'commission_percent' => ['عمولة المنصة %', ''],
                'required_testers' => ['المختبِرون المطلوبون', 'شرط Google: 12'],
                'reserve_testers' => ['مقاعد احتياط', ''],
                'test_days' => ['مدة الاختبار (يوم)', 'لا تقل عن '.config('mihak.google_days')],
                'miss_allowed' => ['أيام الغياب المسموحة', ''],
                'min_open_seconds' => ['أقل استخدام يومي (ثانية)', ''],
                'withdraw_min' => ['حد السحب (د.ع)', ''],
                'seat_confirm_hours' => ['مهلة تأكيد التثبيت (ساعة)', ''],
            ];
        @endphp
        <div class="form-grid">
            @foreach ($fields as $key => [$label, $hint])
                <label class="f" for="{{ $key }}">{{ $label }} @if ($hint)<small>{{ $hint }}</small>@endif
                    <input id="{{ $key }}" name="{{ $key }}" type="number" inputmode="numeric" value="{{ old($key, $values[$key]) }}" required>
                </label>
            @endforeach
        </div>
        @if ($errors->any())
            <div class="callout bad small">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif

        <label class="check"><input type="checkbox" name="failure_pays_completers" value="1" @checked($values['failure_pays_completers'])>
            <span>عند فشل الحملة، ادفع لمن أكمل المدة <span class="muted small">(معطّل حالياً: لا يُدفع لأحد إذا لم يكتمل العدد)</span></span></label>
        <label class="check"><input type="checkbox" name="refund_includes_commission" value="1" @checked($values['refund_includes_commission'])>
            <span>عند الاسترداد، أعِد العمولة أيضاً للمطوّر</span></label>

        <div><button class="btn" type="submit">حفظ الإعدادات</button></div>
    </form>

    <aside class="panel soft">
        <h2>الحساب الحالي</h2>
        <ul class="ticks">
            <li>حصة المنصة: @iqd($terms->platformCut()) من كل تطبيق</li>
            <li>حصة كل مختبِر: @iqd($terms->share)</li>
            <li>يومياً: @iqd($terms->daily()) (اليوم الأخير @iqd($terms->lastDay()))</li>
            <li>اختبارات للوصول إلى حد السحب: {{ $terms->testsToReach($values['withdraw_min']) }}</li>
        </ul>
    </aside>
</div>

{{-- خط الموقع: نحمّل كل الخطوط هنا فقط لعرض المعاينة --}}
<link rel="stylesheet" href="{{ \App\Services\SettingsService::fontUrl(array_keys($fonts)) }}">
<form class="panel" method="POST" action="{{ route('admin.settings.font') }}" style="margin-top:16px">
    @csrf
    @method('PUT')
    <div class="card-top">
        <h2>خط الموقع</h2>
        <span class="muted small">الحالي: {{ $values['site_font'] }}</span>
    </div>
    <p class="muted small">يُطبّق على كل صفحات الموقع فور الحفظ: الصفحة الرئيسية، لوحات المطوّرين والمختبِرين، ولوحة الإدارة.</p>
    <div class="grid-3">
        @foreach ($fonts as $name => $font)
            <label class="panel check" style="padding:14px;cursor:pointer;{{ $values['site_font'] === $name ? 'border-color:var(--accent)' : '' }}">
                <input type="radio" name="site_font" value="{{ $name }}" @checked($values['site_font'] === $name)>
                <span style="font-family:'{{ $name }}',Tahoma,sans-serif;display:grid;gap:4px">
                    <b style="font-size:1.05rem">{{ $font['label'] }}</b>
                    <span style="font-size:1.25rem;font-weight:700">اختبار مغلق حقيقي</span>
                    <span class="muted">حصة المختبِر 1,142 د.ع بعد 16 يوماً</span>
                </span>
            </label>
        @endforeach
    </div>
    <div><button class="btn" type="submit">حفظ الخط</button></div>
</form>
@endsection
