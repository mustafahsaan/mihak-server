@extends('layouts.app')
@section('title', 'دفتر الحركات')

@section('content')
<h1>دفتر الحركات</h1>
<p class="muted small" style="margin-bottom:14px">كل حركة مجموع أطرافها صفر. الموجب = مال دخل الحساب، السالب = مال خرج منه. لا تُعدَّل الحركات بعد كتابتها.</p>

<div class="tbl">
    <table>
        <thead><tr><th>#</th><th>التاريخ</th><th>الوصف</th><th>الأطراف</th></tr></thead>
        <tbody>
        @foreach ($transactions as $t)
            <tr>
                <td class="num">{{ $t->id }}</td>
                <td class="num">{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                <td style="white-space:normal;min-width:220px">{{ $t->description }}<br><span class="muted small">{{ $t->type }}</span></td>
                <td>
                    @foreach ($t->entries as $e)
                        <div class="small num">
                            <span class="{{ $e->amount > 0 ? 'plus' : 'minus' }}">{{ $e->amount > 0 ? '+' : '−' }}{{ number_format(abs($e->amount)) }}</span>
                            {{ \App\Services\LedgerService::LABELS[$e->account] ?? $e->account }}@if ($e->holder_id) <span class="muted">({{ $e->holder_type }} #{{ $e->holder_id }})</span>@endif
                        </div>
                    @endforeach
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@include('admin._pager', ['paginator' => $transactions])
@endsection
