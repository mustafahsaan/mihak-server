@extends('layouts.app')
@section('title', 'دخول')

@section('content')
<div class="auth">
    <form class="panel" method="POST" action="{{ route('login') }}">
        @csrf
        <h1>تسجيل الدخول</h1>
        <label class="f" for="phone">رقم الهاتف
            <input id="phone" name="phone" dir="ltr" inputmode="numeric" autocomplete="tel" placeholder="07XXXXXXXXX" value="{{ old('phone') }}" required>
        </label>
        @error('phone')<p class="err">{{ $message }}</p>@enderror
        <label class="f" for="password">كلمة المرور
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </label>
        <label class="check"><input type="checkbox" name="remember" value="1"> تذكّرني على هذا الجهاز</label>
        <button class="btn" type="submit">دخول</button>
        <p class="small muted">ليس لديك حساب؟ <a href="{{ route('register') }}">حساب مطوّر</a> أو <a href="{{ route('tester.register') }}">حساب مختبِر</a>.</p>
    </form>
</div>
@endsection
