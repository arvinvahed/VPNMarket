@extends('layouts.frontend')

@section('title', $settings->get('cyberpunk_navbar_brand') ?: 'Quantum VPN - دروازه‌ای به اینترنت فردا')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/cyberpunk/css/style.css') }}">
@endpush

@section('content')
    <!-- Particle.js container -->
    <div id="particles-js"></div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">{{ $settings->get('cyberpunk_navbar_brand') ?: 'VPN Market' }}</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#features">قابلیت‌ها</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">سرویس‌ها</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">سوالات</a></li>
                </ul>
                <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">ورود / ثبت‌نام</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section">
        <div class="container">
            @php
                // برای خوانایی بهتر، مقادیر را یک بار می‌خوانیم
                $heroTitle = $settings->get('cyberpunk_hero_title');
                $heroSubtitle = $settings->get('cyberpunk_hero_subtitle');
                $heroButton = $settings->get('cyberpunk_hero_button_text');
            @endphp
            <h1 class="glitch-text" data-text="{{ $heroTitle ?: 'واقعیت را هک کن' }}">
                {{ $heroTitle ?: 'واقعیت را هک کن' }}
            </h1>
            <p class="lead my-4 text-light" style="opacity: 0.8;">
                @if($heroSubtitle)
                    {!! nl2br(e($heroSubtitle)) !!}
                @else
                    با پروتکل‌های کوانتومی ما، مرزهای دیجیتال را جابجا کنید. <br> اتصال شما، قلمرو شماست.
                @endif
            </p>
            <a href="#pricing" class="btn-cyber mt-3">
                {{ $heroButton ?: 'دریافت دسترسی' }}
            </a>
        </div>
    </header>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container text-center">
            <h2 class="section-title mb-5" data-aos="fade-up">
                {{ $settings->get('cyberpunk_features_title') ?: 'سیستم‌عامل آزادی دیجیتال شما' }}
            </h2>
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="cyber-card h-100">
                        <i class="fas fa-fighter-jet icon-glow"></i>
                        <h4>{{ $settings->get('cyberpunk_feature1_title') ?: 'پروتکل Warp' }}</h4>
                        <p>{{ $settings->get('cyberpunk_feature1_desc') ?: 'تکنولوژی انحصاری برای عبور از شدیدترین فیلترینگ‌ها با سرعت نور.' }}</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="cyber-card h-100">
                        <i class="fas fa-fingerprint icon-glow"></i>
                        <h4>{{ $settings->get('cyberpunk_feature2_title') ?: 'حالت Ghost' }}</h4>
                        <p>{{ $settings->get('cyberpunk_feature2_desc') ?: 'ناشناس بمانید. ترافیک شما غیرقابل شناسایی و ردیابی خواهد بود.' }}</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="cyber-card h-100">
                        <i class="fas fa-infinity icon-glow"></i>
                        <h4>{{ $settings->get('cyberpunk_feature3_title') ?: 'اتصال پایدار' }}</h4>
                        <p>{{ $settings->get('cyberpunk_feature3_desc') ?: 'بدون قطعی. سرورهای ما همیشه برای ارائه بهترین پایداری آنلاین هستند.' }}</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="cyber-card h-100">
                        <i class="fas fa-terminal icon-glow"></i>
                        <h4>{{ $settings->get('cyberpunk_feature4_title') ?: 'پشتیبانی Elite' }}</h4>
                        <p>{{ $settings->get('cyberpunk_feature4_desc') ?: 'تیم پشتیبانی متخصص ما، آماده حل چالش‌های فنی شما هستند.' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-5">
        <div class="container">
            <h2 class="section-title text-center mb-5" data-aos="fade-up">
                {{ $settings->get('cyberpunk_pricing_title') ?: 'انتخاب پلن اتصال' }}
            </h2>
            <div class="row justify-content-center">
                @isset($plans)
                    @foreach($plans as $plan)
                        <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="{{ $loop->iteration * 100 }}">
                            <div class="cyber-card text-center h-100 {{ $plan->is_popular ? 'popular' : '' }}">
                                <h4 class="fw-bold" style="{{ $plan->is_popular ? 'color: var(--neon-magenta);' : '' }}">
                                    {{ $plan->name }}
                                </h4>
                                <p class="fs-1 fw-bold">{{ $plan->price }}<span class="fs-5">/{{ $plan->currency }}</span></p>
                                <ul class="list-unstyled my-4">
                                    @foreach(explode("\n", $plan->features) as $feature)
                                        <li>{{ trim($feature) }}</li>
                                    @endforeach
                                </ul>


                                <form method="POST" action="{{ route('order.store', $plan->id) }}">
                                    @csrf

                                    <button type="submit" class="btn-cyber w-100"
                                            style="{{ $plan->is_popular ? 'color:var(--neon-magenta); border-color:var(--neon-magenta); box-shadow: 0 0 10px var(--neon-magenta), inset 0 0 10px var(--neon-magenta);' : '' }}">
                                        فعالسازی
                                    </button>
                                </form>



                            </div>
                        </div>
                    @endforeach
                @endisset
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-5">
        <div class="container w-75">
            <h2 class="section-title text-center mb-5" data-aos="fade-up">
                {{ $settings->get('cyberpunk_faq_title') ?: 'اطلاعات طبقه‌بندی شده' }}
            </h2>
            <div class="accordion" id="faqAccordion" data-aos="fade-up" data-aos-delay="100">
                <div class="accordion-item my-2" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-transparent text-light" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                            {{ $settings->get('cyberpunk_faq1_q') ?: 'آیا اطلاعات کاربران ذخیره می‌شود؟' }}
                        </button>
                    </h2>
                    <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            {{ $settings->get('cyberpunk_faq1_a') ?: 'خیر. ما به حریم خصوصی شما متعهدیم و هیچ‌گونه گزارشی از فعالیت‌های شما (No-Log Policy) ذخیره نمی‌کنیم.' }}
                        </div>
                    </div>
                </div>
                <div class="accordion-item my-2" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-transparent text-light" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                            {{ $settings->get('cyberpunk_faq2_q') ?: 'چگونه می‌توانم سرویس را روی چند دستگاه استفاده کنم؟' }}
                        </button>
                    </h2>
                    <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            {{ $settings->get('cyberpunk_faq2_a') ?: 'پس از خرید، یک کانفیگ دریافت می‌کنید که می‌توانید بر اساس پلن خود، آن را همزمان روی چند دستگاه (موبایل، کامپیوتر و...) استفاده کنید.' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-4">
        <div class="container text-center">
            <p class="mb-0">
                {{ $settings->get('cyberpunk_footer_text') ?: '© 2025 Quantum Network. اتصال برقرار شد.' }}
            </p>
        </div>
    </footer>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script src="{{ asset('themes/cyberpunk/js/main.js') }}"></script>
@endpush
