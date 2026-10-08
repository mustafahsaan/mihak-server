@extends('layouts.app')
@section('title', $campaign->androidApp->name)

@section('content')
<div class="card-top" style="margin-bottom:14px">
    <div>
        <span class="eyebrow">الحملة #{{ $campaign->id }}{{ $campaign->cycle > 1 ? ' · الدورة '.$campaign->cycle : '' }}</span>
        <h1>{{ $campaign->androidApp->name }}</h1>
        <code>{{ $campaign->androidApp->package_name }}</code>
    </div>
    <span class="chip {{ $campaign->status }}">{{ $campaign->label() }}</span>
</div>

<div class="grid-3">
    <div class="kpi"><span class="l">المطوّر</span><span class="v" style="font-size:1.05rem">{{ $campaign->androidApp->developer->name }}</span><span class="s num" dir="ltr">{{ $campaign->androidApp->developer->phone }}</span></div>
    <div class="kpi"><span class="l">رصيد الضمان الحالي</span><span class="v">@iqd($escrow)</span><span class="s">الحصة @iqd($campaign->share) × {{ $campaign->slots }}</span></div>
    <div class="kpi"><span class="l">الأيام</span><span class="v num">{{ $campaign->days_done }} / {{ $campaign->days }}</span><span class="s">@arDate($campaign->started_on) ← @arDate($campaign->endDate())</span></div>
</div>

<div class="head"><h2>المقاعد</h2></div>
<div class="tbl">
    <table>
        <thead><tr><th>المختبِر</th><th>Gmail</th><th>الحالة</th><th>أيام محققة</th><th>غياب</th><th>المكتسب</th><th>انضم</th><th>أكّد التثبيت</th></tr></thead>
        <tbody>
        @forelse ($enrollments as $e)
            <tr>
                <td>{{ $e->tester->name }}<br><span class="muted small num" dir="ltr">{{ $e->tester->phone }}</span></td>
                <td><code>{{ $e->tester->email }}</code></td>
                <td>{{ ['active' => 'نشط', 'completed' => 'أكمل', 'removed' => 'خرج', 'voided' => 'لم يُدفع'][$e->status] ?? $e->status }}@if ($e->end_reason)<br><span class="muted small">{{ $e->reasonLabel() }}</span>@endif</td>
                <td class="num">{{ $e->verified_days }}</td>
                <td class="num">{{ $e->misses }} / {{ $campaign->miss_allowed }}</td>
                <td class="num">@iqd($e->accrued)</td>
                <td>@arDate($e->joined_at)</td>
                <td>{{ $e->installed_confirmed_at ? '✓' : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">لا يوجد مختبِرون بعد.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="grid-2" style="margin-top:16px">
    <div class="panel">
        <h2>الدفعات</h2>
        @forelse ($campaign->payments as $p)
            <p class="small">{{ $p->gatewayLabel() }} · @iqd($p->amount) · <span class="chip {{ $p->status }}">{{ $p->status }}</span> <code>{{ $p->gateway_txn_id }}</code></p>
        @empty
            <p class="muted small">{{ $campaign->parent_campaign_id ? 'إعادة اختبار مموّلة من الدورة #'.$campaign->parent_campaign_id : 'لا توجد دفعات.' }}</p>
        @endforelse
    </div>
    <div class="panel">
        <h2>الملاحظات</h2>
        @forelse ($campaign->feedback as $f)
            <p class="small"><b>{{ $f->tester->name }}:</b> {{ $f->body }}</p>
        @empty
            <p class="muted small">لا توجد ملاحظات.</p>
        @endforelse
    </div>
</div>
@endsection
