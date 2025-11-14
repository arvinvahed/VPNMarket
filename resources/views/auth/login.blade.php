@php
    use App\Models\Setting;

    $settings = Setting::all()->pluck('value', 'key');
    $activeAuthTheme = $settings->get('active_auth_theme', 'default');
@endphp

    <!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - ورود</title>


    <link rel="stylesheet" href="{{ asset('themes/auth/' . $activeAuthTheme . '/css/style.css') }}">
</head>
<body class="{{ $activeAuthTheme }}-auth-body">

<div class="auth-container"><!-- ✅ اضافه شد برای وسط‌چین کردن فرم -->

    <div class="embers-container">
        @for ($i = 0; $i < 20; $i++)
            <div class="ember"></div>
        @endfor
    </div>

    <div class="auth-card">

        <div class="auth-logo">{{ $settings->get('auth_brand_name', 'ARV') }}</div>

        <h2 class="auth-title">ورود به حساب کاربری</h2>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="input-group">
                <input id="email" class="input-field" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="ایمیل خود را وارد کنید">
                <x-input-error :messages="$errors->get('email')" class="input-error-message" />
            </div>

            <div class="input-group">
                <input id="password" class="input-field" type="password" name="password" required autocomplete="current-password" placeholder="رمز عبور">
                <x-input-error :messages="$errors->get('password')" class="input-error-message" />
            </div>

            <div class="form-row mb-4">
                <label for="remember_me" class="remember-me">
                    <input id="remember_me" type="checkbox" name="remember">
                    مرا به خاطر بسپار
                </label>
            </div>

            <div class="input-group">
                <button type="submit" class="btn-submit">ورود</button>
            </div>

            <hr class="separator">

            <div class="register-link">
                حساب کاربری ندارید؟ <a class="auth-link" href="{{ route('register') }}">یک حساب بسازید</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
