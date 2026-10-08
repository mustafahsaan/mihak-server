@extends('layouts.app')
@section('title', 'الاستردادات')

@section('content')
<h1 style="margin-bottom:14px">استردادات المطوّرين</h1>

<div class="tbl">
    <table>
        <thead><tr><th>المطوّر</th><th>التطبيق</th><th>المبلغ</th><th>السبب</th><th>الدفعة الأصلية</th><th>الحالة</th><th>الإجراء</th></tr></thead>
        <tbody>
        @forelse ($refunds as $r)
            <tr>
                <td>{{ $r->user->name }}<br><span class="muted small num" dir="ltr">{{ $r->user->phone }}</span></td>
                <td>{{ $r->campaign->androidApp->name }}</td>
                <td class="num"><b>@iqd($r->amount)</b></td>
                <td>{{ $r->reason === 'duplicate_payment' ? 'دفعة مكررة' : 'لم يكتمل العدد' }}</td>
                <td>{{ $r->payment ? $r->payment->gatewayLabel() : '—' }}@if ($r->payment?->gateway_txn_id)<br><code>{{ $r->payment->gateway_txn_id }}</code>@endif</td>
                <td><span class="chip {{ $r->status }}">{{ $r->label() }}</span>@if ($r->admin_note)<br><span class="muted small">{{ $r->admin_note }}</span>@endif</td>
                <td>
                    @if ($r->status !== 'paid')
                        @if ($r->payment)
                            <form method="POST" action="{{ route('admin.refunds.gateway', $r) }}">@csrf<button class="btn sm" type="submit">استرداد عبر {{ $r->payment->gatewayLabel() }}</button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.refunds.manual', $r) }}" style="margin-top:6px">
                            @csrf
                            <input name="reference" placeholder="رقم الحوالة اليدوية" aria-label="رقم الحوالة" required>
                            <button class="btn ghost sm" type="submit">نُفّذ يدوياً</button>
                        </form>
                    @else
                        <code>{{ $r->gateway_ref }}</code>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">لا توجد استردادات.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('admin._pager', ['paginator' => $refunds])
@endsection
