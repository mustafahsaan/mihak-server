@extends('layouts.app')
@section('title', 'تأكيد الرقم')

@section('content')
<div class="auth stack">
    <form class="panel" method="POST" action="{{ route('otp.verify') }}">
        @csrf
        <h1>أكّد رقم هاتفك</h1>
        <p class="muted">أرسلنا رمزاً من 6 أرقام إلى <span class="num" dir="ltr">{{ $phone }}</span>.</p>
        <label class="f" for="code">رمز التحقق
            <input id="code" name="code" dir="ltr" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required>
        </label>
        @error('code')<p class="err">{{ $message }}</p>@enderror
        <button class="btn" type="submit">تأكيد</button>
    </form>
    <form method="POST" action="{{ route('otp.resend') }}">
        @csrf
        <button class="btn ghost sm" type="submit" id="resend" data-wait="{{ $resendAfter }}" @disabled($resendAfter > 0)>
            لم يصلني الرمز، أرسله مجدداً
        </button>
        <span class="muted small num" id="resend-timer"></span>
    </form>
</div>

<script>
    // عداد إعادة الإرسال: يتحكم بمدته المدير من الإعدادات
    (function () {
        var btn = document.getElementById('resend');
        var label = document.getElementById('resend-timer');
        var left = parseInt(btn.dataset.wait, 10) || 0;
        function tick() {
            if (left <= 0) { btn.disabled = false; label.textContent = ''; return; }
            label.textContent = 'متاح بعد ' + left + ' ثانية';
            left -= 1;
            setTimeout(tick, 1000);
        }
        tick();
    })();
</script>
@endsection
