@extends('layouts.app')
@section('title', 'حساب مطوّر')

@section('content')
<div class="auth">
    <form class="panel" method="POST" action="{{ route('register') }}">
        @csrf
        <h1>حساب مطوّر جديد</h1>
        <label class="f" for="name">الاسم
            <input id="name" name="name" autocomplete="name" value="{{ old('name') }}" required>
        </label>
        @error('name')<p class="err">{{ $message }}</p>@enderror
        <label class="f" for="phone">رقم الهاتف <small>يصلك عليه رمز التحقق</small>
            <input id="phone" name="phone" dir="ltr" inputmode="numeric" autocomplete="tel" placeholder="07XXXXXXXXX" value="{{ old('phone') }}" required>
        </label>
        @error('phone')<p class="err">{{ $message }}</p>@enderror
        <label class="f" for="password">كلمة المرور <small>8 أحرف على الأقل</small>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
        </label>
        <label class="f" for="password_confirmation">تأكيد كلمة المرور
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </label>
        @error('password')<p class="err">{{ $message }}</p>@enderror
        <button class="btn" type="submit">إنشاء الحساب</button>
    </form>
</div>
@endsection
