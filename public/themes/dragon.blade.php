@extends('layouts.frontend')

@section('title', $settings->get('dragon_navbar_brand') ?: 'Ezhdeha VPN - فرمانروای شبکه‌های دیجیتال')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/dragon/css/style.css') }}">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
@endpush

@section('content')

    <div class="video-background">
        <video autoplay muted loop id="bg-video">
            <source src="{{ asset('themes/dragon/video/vd.mp4') }}" type="video/mp4">
            Your browser does not support the video tag.
        </video>
        <div class="video-overlay"></div>
    </div>


    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                @if($settings->get('site_logo'))
                    <img src="{{ asset('storage/' . $settings->get('site_logo')) }}" alt="Logo" class="navbar-logo">
                @endif
                {{ $settings->get('dragon_navbar_brand') ?: 'EZHDEHA VPN' }}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#features">قدرت‌ها</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">پیمان</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">طومارها</a></li>
                </ul>
                <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm btn-dragon-nav">ورود / ثبت‌نام</a>
            </div>
        </div>
    </nav>

    <header class="hero-section">
        <div class="container position-relative z-index-1">
            @php
                $heroTitle = $settings->get('dragon_hero_title');
                $heroSubtitle = $settings->get('dragon_hero_subtitle');
                $heroButton = $settings->get('dragon_hero_button_text');
            @endphp


            <div class="hero-title-wrapper" data-aos="fade-up" data-aos-delay="500">
                <img src="{{ asset('themes/dragon/img/dragon-head-left.png') }}" alt="Dragon Head Left" class="dragon-title-deco dragon-title-left">
                <h1 class="dragon-title">
                    {{ $heroTitle ?: 'مرزهای دیجیتال را بسوزان' }}
                </h1>
                <img src="{{ asset('themes/dragon/img/dragon-head-left.png') }}" alt="Dragon Wing Right" class="dragon-title-deco dragon-title-right">
            </div>


            <p class="lead my-4 text-light hero-subtitle" data-aos="fade-up" data-aos-delay="700">
                @if($heroSubtitle)
                    {!! nl2br(e($heroSubtitle)) !!}
                @else
                    سرعتی افسانه‌ای و امنیتی نفوذناپذیر. <br> سلطه بر اینترنت.
                @endif
            </p>
            <a href="#pricing" class="btn-dragon mt-3" data-aos="zoom-in" data-aos-delay="900">
                {{ $heroButton ?: 'فتح شبکه' }}
            </a>
        </div>
    </header>

    <section id="features" class="py-5 bg-darker-section">
        <div class="container text-center">
            <h2 class="section-title mb-5" data-aos="fade-up">
                {{ $settings->get('dragon_features_title') ?: 'عناصر قدرت اژدها' }}
            </h2>
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="dragon-card h-100">
                        <i class="fas fa-fire icon-dragon-glow"></i>
                        <h4>{{ $settings->get('dragon_feature1_title') ?: 'نفس آتشین (سرعت)' }}</h4>
                        <p>{{ $settings->get('dragon_feature1_desc') ?: 'سریع‌تر از یک بال اژدها. داده‌های شما با بالاترین سرعت منتقل می‌شوند.' }}</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="dragon-card h-100">
                        <i class="fas fa-shield-alt icon-dragon-glow"></i>
                        <h4>{{ $settings->get('dragon_feature2_title') ?: 'زره فلس‌دار (امنیت)' }}</h4>
                        <p>{{ $settings->get('dragon_feature2_desc') ?: 'دیوارهای فیلترینگ شکسته می‌شوند. هویت شما همیشه پنهان می‌ماند.' }}</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="dragon-card h-100">
                        <i class="fas fa-eye icon-dragon-glow"></i>
                        <h4>{{ $settings->get('dragon_feature3_title') ?: 'بینایی فراتر (آزادی)' }}</h4>
                        <p>{{ $settings->get('dragon_feature3_desc') ?: 'کل شبکه در اختیار شماست. هیچ مرزی وجود نخواهد داشت.' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="pricing" class="py-5">
        <div class="container">
            <h2 class="section-title text-center mb-5" data-aos="fade-up">
                {{ $settings->get('dragon_pricing_title') ?: 'پیمان خون' }}
            </h2>
            <div class="row justify-content-center">
                @isset($plans)
                    @foreach($plans as $plan)
                        <div class="col-lg-4 mb-4" data-aos="zoom-in-up" data-aos-delay="{{ $loop->iteration * 150 }}">
                            <div class="dragon-card text-center h-100 {{ $plan->is_popular ? 'popular' : '' }}">
                                <h4 class="fw-bold" style="{{ $plan->is_popular ? 'color: var(--neon-gold);' : '' }}">
                                    {{ $plan->name }}
                                </h4>
                                <p class="fs-1 fw-bold">{{ $plan->price }}<span class="fs-5">/{{ $plan->currency }}</span></p>
                                <ul class="list-unstyled my-4 plan-features">
                                    @foreach(explode("\n", $plan->features) as $feature)
                                        <li><i class="fas fa-check-circle me-2"></i>{{ trim($feature) }}</li>
                                    @endforeach
                                </ul>

                                <form method="POST" action="{{ route('order.store', $plan->id) }}">
                                    @csrf
                                    <button type="submit" class="btn-dragon w-100"
                                            style="{{ $plan->is_popular ? 'color:var(--neon-gold); border-color:var(--neon-gold); box-shadow: 0 0 10px var(--neon-gold), inset 0 0 10px var(--neon-gold);' : '' }}">
                                        عقد پیمان
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endisset
            </div>
        </div>
    </section>

    <section id="faq" class="py-5 bg-darker-section">
        <div class="container w-75">
            <h2 class="section-title text-center mb-5" data-aos="fade-up">
                {{ $settings->get('dragon_faq_title') ?: 'طومارهای باستانی' }}
            </h2>
            <div class="accordion" id="faqAccordion" data-aos="fade-up" data-aos-delay="100">
                <div class="accordion-item my-2 dragon-accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-transparent text-light dragon-accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                            {{ $settings->get('dragon_faq1_q') ?: 'آیا این سرویس باستانی است؟' }}
                        </button>
                    </h2>
                    <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            {{ $settings->get('dragon_faq1_a') ?: 'خیر، این شبکه باستانی نیست اما از قدرت‌های افسانه‌ای اژدهایان برای شکستن سدها و اتصال شما استفاده می‌کند. سریع، امن و پایدار.' }}
                        </div>
                    </div>
                </div>
                <div class="accordion-item my-2 dragon-accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-transparent text-light dragon-accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                            {{ $settings->get('dragon_faq2_q') ?: 'چگونه قدرت اژدها را فعال کنم؟' }}
                        </button>
                    </h2>
                    <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            {{ $settings->get('dragon_faq2_a') ?: 'کافیست پلن مورد نظر خود را انتخاب کرده و مراحل پرداخت را طی کنید. کانفیگ نهایی به شما تحویل داده می‌شود تا به راحتی از تمام قدرت اژدها استفاده کنید.' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer py-4 dragon-footer">
        <div class="container text-center">
            <p class="mb-0">
                {{ $settings->get('dragon_footer_text') ?: '© 2025 Ezhdeha Networks. قدرت آتشین.' }}
            </p>
        </div>
    </footer>
@endsection

@push('scripts')
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true,
        });
    </script>
    <script src="{{ asset('themes/dragon/js/main.js') }}"></script>
@endpush
