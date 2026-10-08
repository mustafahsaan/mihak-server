@extends('layouts.app')
@section('title', 'تطبيقاتي')

@section('content')
<div class="grid-2">
    <form class="panel" method="POST" action="{{ route('apps.store') }}">
        @csrf
        <h2>أضف تطبيقاً للاختبار</h2>
        <label class="f" for="name">اسم التطبيق
            <input id="name" name="name" value="{{ old('name') }}" placeholder="مثال: دليل محطات الوقود" required>
        </label>
        @error('name')<p class="err">{{ $message }}</p>@enderror
        <label class="f" for="package_name">اسم الحزمة (Package name)
            <input id="package_name" name="package_name" dir="ltr" value="{{ old('package_name') }}" placeholder="com.example.app" required>
        </label>
        @error('package_name')<p class="err">{{ $message }}</p>@enderror
        <label class="f" for="opt_in_url">رابط الاشتراك في الاختبار المغلق <small>اختياري، تجده في Play Console</small>
            <input id="opt_in_url" name="opt_in_url" dir="ltr" value="{{ old('opt_in_url') }}" placeholder="https://play.google.com/apps/testing/com.example.app">
        </label>
        @error('opt_in_url')<p class="err">{{ $message }}</p>@enderror
        <div><button class="btn" type="submit">متابعة إلى الدفع · @iqd($terms->price)</button></div>
    </form>

    <aside class="panel soft">
        <h2>ضمانك كمطوّر</h2>
        <ul class="ticks">
            <li>لا يُصرف أي دينار للمختبِرين قبل اكتمال {{ $terms->days }} يوماً.</li>
            <li>{{ $terms->slots - $terms->required }} مقاعد احتياط: إذا خرج مختبِر يبقى العدد {{ $terms->required }}.</li>
            <li>إذا أكمل أقل من {{ $terms->required }} تختار: استرداد المبلغ أو إعادة الاختبار مجاناً.</li>
            <li>كل مختبِر يفتح تطبيقك يومياً، وتطبيقنا يتحقق من ذلك تلقائياً.</li>
        </ul>
        <div class="callout small">قبل الدفع جهّز في Play Console إصداراً على مسار «الاختبار المغلق»، وأضف المجموعة <code>{{ $groupEmail }}</code> إلى قائمة المختبِرين.</div>
    </aside>
</div>

<div class="head"><h2>تطبيقاتي</h2><span class="muted">{{ $apps->count() }}</span></div>

@if ($apps->isEmpty())
    <p class="panel muted">لم تضف أي تطبيق بعد.</p>
@else
    <div class="cards">
        @foreach ($apps as $app)
            @php($campaign = $app->campaigns->first())
            <div class="card">
                <div class="card-top">
                    <div><h3>{{ $app->name }}</h3><code>{{ $app->package_name }}</code></div>
                    @if ($campaign)<span class="chip {{ $campaign->status }}">{{ $campaign->label() }}</span>@endif
                </div>
                @if ($campaign)
                    <div class="meta">
                        @if ($campaign->status === 'testing' && $campaign->started_on)
                            <span>اليوم <b class="num">{{ min($campaign->days_done + 1, $campaign->days) }} / {{ $campaign->days }}</b></span>
                        @endif
                        @if (in_array($campaign->status, ['recruiting', 'testing']))
                            <span>المقاعد <b class="num">{{ $campaign->active_count }} / {{ $campaign->slots }}</b></span>
                        @endif
                        @if ($campaign->cycle > 1)<span>الدورة {{ $campaign->cycle }}</span>@endif
                        @if ($campaign->feedback_count)<span>{{ $campaign->feedback_count }} ملاحظات</span>@endif
                    </div>
                    <div class="row">
                        @if ($campaign->status === 'unpaid')
                            <a class="btn money sm" href="{{ route('campaigns.pay', $campaign) }}">ادفع @iqd($campaign->price)</a>
                        @else
                            <a class="btn ghost sm" href="{{ route('campaigns.show', $campaign) }}">تفاصيل الاختبار</a>
                        @endif
                        @if (in_array($campaign->status, ['completed', 'refunded']))
                            <form method="POST" action="{{ route('apps.campaigns.store', $app) }}">@csrf<button class="btn ghost sm" type="submit">اختبار جديد لهذا التطبيق</button></form>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
@endsection
