@extends('layouts.frontend')

@section('title', 'خوش آمدید - VPNMarket')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/welcome/css/style.css') }}">
@endpush

@section('content')
    <div class="welcome-box" data-aos="fade-up">
        <h1>نصب با موفقیت انجام شد!</h1>
        <p>
            به <span class="brand">VPNMarket</span> خوش آمدید.
            <br>
            برای شروع و انتخاب قالب اصلی وب‌سایت، لطفاً از طریق دکمه زیر وارد پنل مدیریت شوید.
        </p>
        <a href="/admin" class="btn-admin-panel">
            <i class="fas fa-cogs me-2"></i>
            ورود به پنل مدیریت
        </a>
    </div>
@endsection

@push('scripts')

    <script>
        AOS.init({
            duration: 800,
            once: true,
        });
    </script>
@endpush
