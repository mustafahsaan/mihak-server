@extends('layouts.app')
@section('title', $app->name)

@section('content')
<div class="card-top" style="margin-bottom:16px">
    <div>
        <span class="eyebrow">اختبار مغلق{{ $campaign->cycle > 1 ? ' · الدورة '.$campaign->cycle : '' }}</span>
        <h1>{{ $app->name }}</h1>
        <code>{{ $app->package_name }}</code>
    </div>
    <span class="chip {{ $campaign->status }}">{{ $campaign->label() }}</span>
</div>

<div class="grid-2">
    <div class="stack">
        @if ($campaign->status === 'unpaid')
            <div class="panel">
                <p>لم يُدفع هذا الاختبار بعد.</p>
                <div><a class="btn money" href="{{ route('campaigns.pay', $campaign) }}">ادفع @iqd($campaign->price)</a></div>
            </div>
        @endif

        @if ($campaign->status === 'recruiting')
            <div class="panel">
                <h2>نجمع المختبِرين</h2>
                <div class="meta">
                    <span>المقاعد المحجوزة <b class="num">{{ $seats->where('status', 'active')->count() }} / {{ $campaign->slots }}</b></span>
                    <span>أكّدوا التثبيت <b class="num">{{ $seats->where('status', 'active')->whereNotNull('installed_confirmed_at')->count() }}</b></span>
                </div>
                @include('dev._seats')
                <p class="muted small">يبدأ عدّ الأيام من اليوم التالي لتأكيد آخر مختبِر تثبيت التطبيق.</p>
                <div class="callout small">تأكد أن المجموعة <code>{{ $groupEmail }}</code> مضافة إلى قائمة المختبِرين في الاختبار المغلق، وإلا لن يستطيع أحد الاشتراك.</div>
            </div>
        @endif

        @if (in_array($campaign->status, ['testing', 'completed', 'failed', 'refunded', 'rerun']) && $campaign->started_on)
            <div class="panel">
                <div class="card-top">
                    <h2>أيام الاختبار</h2>
                    <span class="muted small">@arDate($campaign->started_on) ← @arDate($campaign->endDate())</span>
                </div>
                @if ($campaign->status === 'testing')
                    <div class="meta">
                        <span>أُغلق <b class="num">{{ $campaign->days_done }}</b> يوماً من {{ $campaign->days }}</span>
                        <span>مشتركون الآن <b class="num">{{ $seats->where('status', 'active')->count() }}</b> (المطلوب {{ $campaign->required }})</span>
                    </div>
                @endif
                @include('dev._strip')
                @include('dev._seats')

                @if ($campaign->status === 'testing' && $campaign->days_done >= $googleDays)
                    <div class="callout good">مرّت {{ $googleDays }} يوماً متواصلة. قدّم الآن طلب الوصول إلى الإنتاج من Play Console، والمختبِرون ما زالوا مشتركين.</div>
                @elseif ($campaign->status === 'testing' && $seats->where('status', 'active')->count() < $campaign->required)
                    <div class="callout bad">انخفض العدد عن {{ $campaign->required }}. عند انتهاء المدة تختار بين الاسترداد وإعادة الاختبار مجاناً.</div>
                @elseif ($campaign->status === 'completed')
                    <div class="callout good">اكتمل الاختبار بـ {{ $campaign->completers }} مختبِراً. إذا لم تقدّم طلب الإنتاج بعد، قدّمه الآن.</div>
                @endif
            </div>
        @endif

        @if ($campaign->status === 'failed')
            <div class="panel" style="border-color:var(--warn)">
                <h2>لم يكتمل عدد المختبِرين</h2>
                <p>أكمل {{ $campaign->completers }} مختبِراً من {{ $campaign->required }} المطلوبين، فلن يُدفع لأحد منهم. اختر ما تريد:</p>
                <div class="grid-2">
                    <form class="panel" method="POST" action="{{ route('campaigns.rerun', $campaign) }}">
                        @csrf
                        <h3>إعادة الاختبار مجاناً</h3>
                        <p class="muted small">نجمع {{ $campaign->slots }} مختبِرين جدداً لدورة كاملة من {{ $campaign->days }} يوماً، دون أي دفعة إضافية.</p>
                        <button class="btn" type="submit">أعد الاختبار</button>
                    </form>
                    <form class="panel" method="POST" action="{{ route('campaigns.refund', $campaign) }}">
                        @csrf
                        <h3>استرداد المبلغ</h3>
                        <p class="muted small">يعود إليك @iqd($refundPreview) على وسيلة الدفع نفسها.</p>
                        <button class="btn ghost" type="submit">استرد المبلغ</button>
                    </form>
                </div>
            </div>
        @endif

        @if ($campaign->status === 'refunded')
            <div class="callout">اخترت الاسترداد. تتابع الإدارة إعادة المبلغ إلى وسيلة الدفع.</div>
        @endif
    </div>

    <aside class="stack">
        <div class="panel">
            <h2>ملاحظات المختبِرين</h2>
            @if ($campaign->feedback->isEmpty())
                <p class="muted small">لم تصل ملاحظات بعد.</p>
            @else
                <ul class="feedback">
                    @foreach ($campaign->feedback as $note)
                        <li><span class="muted small">@arDate($note->created_at) · </span>{{ $note->body }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="panel">
            <h3>روابط مفيدة</h3>
            <p class="small">رابط الاشتراك: <a href="{{ $app->optInUrl() }}" dir="ltr">{{ $app->optInUrl() }}</a></p>
            <p class="small">مجموعة المختبِرين: <code>{{ $groupEmail }}</code></p>
        </div>
    </aside>
</div>
@endsection
