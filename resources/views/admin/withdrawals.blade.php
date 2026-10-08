@extends('layouts.app')
@section('title', 'السحوبات')

@section('content')
<div class="head" style="margin-top:0">
    <h1>طلبات السحب</h1>
    <nav class="nav">
        @foreach (['pending' => 'بانتظار المراجعة', 'processing' => 'قيد التحويل', 'paid' => 'حُوّل', 'rejected' => 'مرفوض', 'all' => 'الكل'] as $key => $label)
            <a href="{{ route('admin.withdrawals', ['status' => $key]) }}" @class(['on' => $status === $key])>{{ $label }}</a>
        @endforeach
    </nav>
</div>

@if ($status === 'pending')
    <p class="callout small" style="margin-bottom:12px">
        @if ($autoZainCash)
            طلبات زين كاش تُصرف آلياً عند الموافقة. طلبات كي كارد: حوّل يدوياً ثم اكتب رقم الحوالة ووافق.
        @else
            حوّل المبلغ إلى حساب المختبِر يدوياً، ثم اكتب رقم الحوالة واضغط «تم التحويل».
        @endif
    </p>
@endif

<div class="tbl">
    <table>
        <thead><tr><th>المختبِر</th><th>المبلغ</th><th>الطريقة</th><th>الحساب</th><th>التاريخ</th><th>الحالة</th><th>الإجراء</th></tr></thead>
        <tbody>
        @forelse ($withdrawals as $w)
            <tr>
                <td>{{ $w->user->name }}<br><span class="muted small num" dir="ltr">{{ $w->user->phone }}</span></td>
                <td class="num"><b>@iqd($w->amount)</b></td>
                <td>{{ $w->methodLabel() }}</td>
                <td><code>{{ $w->account }}</code></td>
                <td>@arDate($w->created_at)</td>
                <td><span class="chip {{ $w->status }}">{{ $w->label() }}</span>@if ($w->admin_note)<br><span class="muted small">{{ $w->admin_note }}</span>@endif</td>
                <td>
                    @if ($w->status === 'pending')
                        <form method="POST" action="{{ route('admin.withdrawals.approve', $w) }}">
                            @csrf
                            <input name="transfer_ref" placeholder="رقم الحوالة" aria-label="رقم الحوالة">
                            <button class="btn sm" type="submit">تم التحويل</button>
                        </form>
                        <form method="POST" action="{{ route('admin.withdrawals.reject', $w) }}" style="margin-top:6px">
                            @csrf
                            <input name="note" placeholder="سبب الرفض" aria-label="سبب الرفض" required>
                            <button class="btn ghost sm" type="submit">رفض</button>
                        </form>
                    @elseif ($w->gateway_ref)
                        <code>{{ $w->gateway_ref }}</code>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">لا توجد طلبات.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('admin._pager', ['paginator' => $withdrawals])
@endsection
