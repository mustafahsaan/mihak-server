@extends('layouts.app')
@section('title', 'الإدارة')

@section('content')
<span class="eyebrow">لوحة الإدارة</span>
<h1 style="margin-bottom:14px">الأموال والاختبارات</h1>

<div class="grid-3">
    <div class="kpi"><span class="l">مدفوعات المطوّرين</span><span class="v">@iqd($totals['received'])</span><span class="s">{{ $counts['paid_apps'] }} دفعة ناجحة</span></div>
    <div class="kpi"><span class="l">محجوز في الضمان</span><span class="v">@iqd($totals['escrow'])</span><span class="s">منه @iqd($accrued) مكتسب معلّق</span></div>
    <div class="kpi"><span class="l">أرصدة المختبِرين</span><span class="v" style="color:var(--money)">@iqd($totals['wallet'])</span><span class="s">{{ $counts['testers'] }} مختبِراً</span></div>
    <div class="kpi"><span class="l">صندوق الضمان</span><span class="v">@iqd($totals['fund'])</span><span class="s">حصص المنسحبين وأيام الغياب</span></div>
    <div class="kpi"><span class="l">أرباح المنصة</span><span class="v">@iqd($totals['revenue'])</span><span class="s">{{ $counts['developers'] }} مطوّراً</span></div>
    <div class="kpi"><span class="l">حُوّل للخارج</span><span class="v">@iqd($totals['paid_out'])</span><span class="s">سحوبات واستردادات منفّذة</span></div>
</div>

<div class="panel" style="margin-top:14px;{{ $integrity['ok'] ? 'background:var(--good-soft);border-color:transparent' : 'background:var(--bad-soft);border-color:transparent' }}">
    <b>{{ $integrity['ok'] ? '✓ الحسابات متوازنة' : '✗ يوجد خلل في الحسابات، راجع دفتر الحركات' }}</b>
    <ul class="ticks small">
        @foreach ($integrity['checks'] as $check)
            <li>{{ $check['ok'] ? '' : '✗ ' }}{{ $check['label'] }} <span class="muted num">({{ $check['detail'] }})</span></li>
        @endforeach
    </ul>
</div>

<div class="grid-3" style="margin-top:14px">
    <a class="card" href="{{ route('admin.withdrawals') }}"><span class="muted small">طلبات سحب بانتظارك</span><h2 class="num">{{ $pendingWithdrawals }}</h2><span class="small muted">قيد التحويل: @iqd($totals['withdrawal_hold'])</span></a>
    <a class="card" href="{{ route('admin.refunds') }}"><span class="muted small">استردادات بانتظار التنفيذ</span><h2 class="num">{{ $pendingRefunds }}</h2><span class="small muted">محجوز للمطوّرين: @iqd($totals['refund_hold'])</span></a>
    <a class="card" href="{{ route('admin.campaigns') }}"><span class="muted small">الحملات</span><h2 class="num">{{ $counts['testing'] }} جارية</h2><span class="small muted">{{ $counts['recruiting'] }} تجمع مختبِرين · {{ $counts['failed'] }} بانتظار قرار المطوّر</span></a>
</div>

@if ($counts['mismatch'])
    <div class="callout bad" style="margin-top:14px">{{ $counts['mismatch'] }} دفعة وصل فيها مبلغ مختلف عن المطلوب. راجعها في الحملات.</div>
@endif
@endsection
