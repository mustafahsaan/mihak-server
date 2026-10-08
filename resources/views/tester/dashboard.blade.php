@extends('layouts.app')
@section('title', 'حسابي')

@section('content')
<div class="card-top" style="margin-bottom:16px">
    <div>
        <span class="muted small">مرحباً</span>
        <h1>{{ $user->name }}</h1>
    </div>
    <span class="chip">{{ $completed }} اختبارات مكتملة</span>
</div>

@unless ($deviceLinked)
    <div class="callout warn" style="margin-bottom:16px">
        <b>خطوة أخيرة:</b> حمّل تطبيق مِحَكّ على هاتف أندرويد وسجّل الدخول بنفس الرقم. الانضمام للاختبارات والتحقق اليومي يتمان من التطبيق.
        @if ($appUrl)<div style="margin-top:8px"><a class="btn money sm" href="{{ $appUrl }}">حمّل التطبيق</a></div>@endif
    </div>
@endunless

<div class="grid-2">
    <div class="stack">
        <section class="wallet">
            <span class="w-l">رصيدك الافتراضي</span>
            <span class="w-v num">@iqd($wallet['balance'])</span>
            @php($pct = $wallet['withdraw_min'] > 0 ? min(100, $wallet['balance'] / $wallet['withdraw_min'] * 100) : 100)
            <div class="w-bar" role="progressbar" aria-label="التقدم نحو حد السحب" aria-valuemin="0" aria-valuemax="{{ $wallet['withdraw_min'] }}" aria-valuenow="{{ min($wallet['balance'], $wallet['withdraw_min']) }}"><span style="width:{{ $pct }}%"></span></div>
            <div class="w-row">
                <span>{{ $wallet['can_withdraw'] ? 'وصلت حد السحب' : 'تبقّى '.number_format($wallet['remaining_to_min']).' د.ع لحد السحب' }}</span>
                <span class="num">{{ number_format($wallet['withdraw_min']) }}</span>
            </div>
            <div class="w-row w-sep"><span>معلّق في اختبارات جارية</span><b class="num">@iqd($wallet['pending'])</b></div>
            @if ($wallet['on_hold'] > 0)
                <div class="w-row"><span>قيد التحويل</span><b class="num">@iqd($wallet['on_hold'])</b></div>
            @endif
        </section>

        <form class="panel" method="POST" action="{{ route('tester.withdraw') }}">
            @csrf
            <h2>سحب الرصيد</h2>
            @if ($pendingWithdrawal)
                <p class="callout small">لديك طلب سحب بـ @iqd($pendingWithdrawal->amount) {{ $pendingWithdrawal->label() }}. يمكنك طلب سحب جديد بعد تنفيذه.</p>
            @elseif (! $wallet['can_withdraw'])
                <p class="muted small">السحب متاح عندما يصل رصيدك @iqd($wallet['withdraw_min']).</p>
            @else
                <div class="form-grid">
                    <label class="f" for="method">طريقة الاستلام
                        <select id="method" name="method">
                            <option value="zaincash" @selected(old('method') === 'zaincash')>زين كاش</option>
                            <option value="qicard" @selected(old('method') === 'qicard')>كي كارد</option>
                        </select>
                    </label>
                    <label class="f" for="account">رقم المحفظة أو البطاقة
                        <input id="account" name="account" dir="ltr" inputmode="numeric" placeholder="07XXXXXXXXX" value="{{ old('account') }}" required>
                    </label>
                    <label class="f" for="amount">المبلغ (د.ع) <small>الحد الأدنى {{ number_format($wallet['withdraw_min']) }}</small>
                        <input id="amount" name="amount" type="number" inputmode="numeric" min="{{ $wallet['withdraw_min'] }}" max="{{ $wallet['balance'] }}" value="{{ old('amount', $wallet['balance']) }}" required>
                    </label>
                </div>
                @if ($errors->any())
                    <div class="callout bad small">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
                @endif
                <div><button class="btn money" type="submit">أرسل طلب السحب</button></div>
            @endif
        </form>
    </div>

    <div class="stack">
        <div class="panel">
            <h2>اختباراتي الجارية</h2>
            @forelse ($enrollments as $row)
                @php($c = $row['campaign'])
                @php($e = $row['enrollment'])
                <div class="card">
                    <div class="card-top">
                        <div><b>{{ $c->androidApp->name }}</b><br><code>{{ $c->androidApp->package_name }}</code></div>
                        @if ($row['day'])
                            <span class="chip {{ $row['opened'] ? 'good' : 'pending' }}">{{ $row['opened'] ? 'حُسب اليوم' : 'لم يُفتح اليوم' }}</span>
                        @else
                            <span class="chip recruiting">بانتظار البدء</span>
                        @endif
                    </div>
                    <div class="meta">
                        @if ($row['day'])
                            <span>اليوم <b class="num">{{ $row['day'] }} / {{ $c->days }}</b></span>
                        @elseif (! $e->installed_confirmed_at)
                            <span>أكمل خطوات الانضمام من التطبيق</span>
                        @elseif ($c->status === 'testing')
                            <span>يبدأ @arDate($c->started_on)</span>
                        @else
                            <span>ننتظر اكتمال المقاعد</span>
                        @endif
                        <span>معلّق <b class="num">@iqd($e->accrued)</b> من @iqd($c->share)</span>
                        @if ($e->misses)<span class="chip warn">غياب {{ $e->misses }} من {{ $c->miss_allowed }}</span>@endif
                    </div>
                    @if ($row['day'])
                        <div class="bar" aria-hidden="true"><span style="width:{{ $c->days_done / $c->days * 100 }}%"></span></div>
                    @endif
                </div>
            @empty
                <p class="muted small">لست في أي اختبار الآن. انضم من تطبيق الهاتف.</p>
            @endforelse
        </div>

        <div class="panel">
            <div class="card-top"><h2>اختبارات متاحة</h2><span class="muted small">انضم إليها من التطبيق</span></div>
            @forelse ($available as $c)
                <div class="row" style="justify-content:space-between;border-bottom:1px solid var(--line);padding-block:6px">
                    <span><b>{{ $c->androidApp->name }}</b> <span class="muted small">· {{ $c->slots - $c->taken }} مقاعد</span></span>
                    <span class="num small">@iqd($c->share)</span>
                </div>
            @empty
                <p class="muted small">لا توجد اختبارات متاحة الآن.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="head"><h2>السجل</h2></div>
<div class="tbl">
    <table>
        <thead><tr><th>العملية</th><th>التاريخ</th><th>المبلغ</th></tr></thead>
        <tbody>
        @forelse ($history as $item)
            <tr>
                <td style="white-space:normal">{{ $item['title'] }}</td>
                <td class="num">{{ $item['date'] }}</td>
                <td class="num {{ $item['amount'] >= 0 ? 'plus' : 'minus' }}">{{ $item['amount'] >= 0 ? '+' : '−' }}{{ number_format(abs($item['amount'])) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">لا توجد حركات بعد.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<p class="muted small" style="margin-top:16px">القواعد: افتح كل تطبيق مختبَر يومياً، ويُسمح بغياب {{ $missAllowed }} أيام في كل اختبار. إلغاء التثبيت أو الانسحاب يلغي المعلّق، وإذا لم يكتمل عدد المختبِرين في نهاية الاختبار لا يُدفع لأحد فيه.</p>
@endsection
