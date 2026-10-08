@extends('layouts.app')
@section('title', 'دفع تجريبي')

@section('content')
<div class="auth">
    <div class="panel">
        <span class="eyebrow">بوابة دفع تجريبية</span>
        <h1>@iqd($payment->amount)</h1>
        <p class="muted">هذه الصفحة تظهر فقط عندما يكون MIHAK_FAKE_PAYMENTS=true. على السيرفر الحقيقي يُحوَّل المطوّر إلى صفحة {{ $payment->gatewayLabel() }}.</p>
        <form method="POST" action="{{ route('fake-gateway.complete', $payment) }}" class="row">
            @csrf
            <button class="btn money" name="outcome" value="pay" type="submit">ادفع</button>
            <button class="btn ghost" name="outcome" value="cancel" type="submit">إلغاء</button>
        </form>
    </div>
</div>
@endsection
