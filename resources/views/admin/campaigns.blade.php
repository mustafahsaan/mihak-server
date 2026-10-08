@extends('layouts.app')
@section('title', 'الحملات')

@section('content')
<div class="head" style="margin-top:0">
    <h1>الحملات</h1>
    <nav class="nav">
        <a href="{{ route('admin.campaigns') }}" @class(['on' => ! $status])>الكل</a>
        @foreach (\App\Models\Campaign::LABELS as $key => $label)
            <a href="{{ route('admin.campaigns', ['status' => $key]) }}" @class(['on' => $status === $key])>{{ $label }}</a>
        @endforeach
    </nav>
</div>

<div class="tbl">
    <table>
        <thead><tr><th>#</th><th>التطبيق</th><th>المطوّر</th><th>الحالة</th><th>اليوم</th><th>المقاعد</th><th>السعر</th><th>دُفع</th></tr></thead>
        <tbody>
        @forelse ($campaigns as $c)
            <tr>
                <td class="num"><a href="{{ route('admin.campaigns.show', $c) }}">{{ $c->id }}</a></td>
                <td><a href="{{ route('admin.campaigns.show', $c) }}">{{ $c->androidApp->name }}</a>@if ($c->cycle > 1) <span class="muted small">· الدورة {{ $c->cycle }}</span>@endif</td>
                <td>{{ $c->androidApp->developer->name }}</td>
                <td><span class="chip {{ $c->status }}">{{ $c->label() }}</span></td>
                <td class="num">{{ $c->status === 'testing' ? $c->days_done.'/'.$c->days : '—' }}</td>
                <td class="num">{{ $c->active_count }}/{{ $c->slots }}</td>
                <td class="num">@iqd($c->price)</td>
                <td>@arDate($c->paid_at)</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">لا توجد حملات.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('admin._pager', ['paginator' => $campaigns])
@endsection
