@extends('layouts.app')
@section('title', 'سجّل كمختبِر')

@section('content')
<div class="grid-2" style="align-items:start">
    <form class="panel" method="POST" action="{{ route('tester.register') }}">
        @csrf
        <span class="eyebrow">للمختبِرين</span>
        <h1>اربح من اختبار التطبيقات</h1>
        <label class="f" for="name">الاسم
            <input id="name" name="name" autocomplete="name" value="{{ old('name') }}" required>
        </label>
        @error('name')<p class="err">{{ $message }}</p>@enderror
        <label class="f" for="phone">رقم الهاتف
            <input id="phone" name="phone" dir="ltr" inputmode="numeric" autocomplete="tel" placeholder="07XXXXXXXXX" value="{{ old('phone') }}" required>
        </label>
        @error('phone')<p class="err">{{ $message }}</p>@enderror
        <label class="f" for="email">حساب Gmail <small>نفس الحساب الموجود في Google Play على هاتفك</small>
            <input id="email" name="email" type="email" dir="ltr" autocomplete="email" placeholder="name@gmail.com" value="{{ old('email') }}" required>
        </label>
        @error('email')<p class="err">{{ $message }}</p>@enderror
        <label class="f" for="password">كلمة المرور <small>8 أحرف على الأقل</small>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
        </label>
        <label class="f" for="password_confirmation">تأكيد كلمة المرور
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </label>
        @error('password')<p class="err">{{ $message }}</p>@enderror
        <button class="btn" type="submit">إنشاء حساب مختبِر</button>
        <p class="small muted">لديك حساب؟ <a href="{{ route('login') }}">سجّل الدخول</a></p>
    </form>

    <aside class="stack">
        <div class="panel soft">
            <h2>كيف تربح</h2>
            <ol class="steps">
                <li><span>أنشئ حسابك هنا، ثم حمّل تطبيق مِحَكّ على هاتف أندرويد وسجّل الدخول بنفس الرقم.</span></li>
                <li><span>انضم لاختبار من التطبيق وثبّت التطبيق المطلوب من Google Play.</span></li>
                <li><span>افتحه مرة يومياً لمدة {{ $terms->days }} يوماً. التطبيق يسجّل ذلك تلقائياً.</span></li>
                <li><span>بعد انتهاء الاختبار تُضاف @iqd($terms->share) إلى رصيدك، وتسحب من الموقع أو التطبيق عند وصوله @iqd($withdrawMin).</span></li>
            </ol>
        </div>
        <p class="callout small">الحساب مربوط بهاتف واحد: أول هاتف تسجّل منه الدخول في التطبيق يصبح هاتف حسابك.</p>
    </aside>
</div>
@endsection
