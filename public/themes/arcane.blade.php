@extends('layouts.frontend')

@section('title', $settings->get('arcane_navbar_brand') ?: 'Arcane VPN')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/arcane/css/style.css') }}">
@endpush

@section('content')
    <!-- Background Particles -->
    <div id="particles-js"></div>

    <div class="content-wrapper">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top py-3 navbar-glass">
            <div class="container">
                <a class="navbar-brand fw-bold" href="#">{{ $settings->get('arcane_navbar_brand') ?: 'ARCANE' }}</a>
                <a href="{{ auth()->check() ? route('filament.admin.pages.dashboard') : route('login') }}" class="btn btn-outline-info btn-sm">{{ auth()->check() ? 'پنل کاربری' : 'ورود / ثبت‌نام' }}</a>
            </div>
        </nav>

        <!-- Hero Section -->
        <header class="hero-section">
            <div class="container" data-aos="fade-up">
                <h1 class="display-3 arcane-title mb-4">{{ $settings->get('arcane_hero_title') ?: 'کدگشایی اینترنت آزاد' }}</h1>
                <p class="lead text-secondary mb-5 mx-auto" style="max-width: 600px;">
                    {{ $settings->get('arcane_hero_subtitle') ?: 'با پروتکل‌های رمزنگاری شده ما، به شبکه‌ای فراتر از محدودیت‌ها متصل شوید. امنیت شما، جادوی ماست.' }}
                </p>
                <a href="#pricing" class="btn-arcane">{{ $settings->get('arcane_hero_button') ?: 'دسترسی به شبکه' }}</a>
            </div>
        </header>

        <!-- Features Section -->
        <section id="features" class="py-5">
            <div class="container text-center">
                <div class="text-center mb-5" data-aos="fade-up">
                    <h2 class="section-title">{{ $settings->get('arcane_features_title') ?: 'اصول جادوی دیجیتال' }}</h2>
                </div>
                <div class="row">
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="arcane-card h-100">
                            <div class="icon-glow"><i class="fas fa-bolt"></i></div>
                            <h4 class="fw-bold">{{ $settings->get('arcane_feature1_title') ?: 'پروتکل‌های کوانتومی' }}</h4>
                            <p class="text-secondary">{{ $settings->get('arcane_feature1_desc') ?: 'اتصال از طریق الگوریتم‌های پیشرفته برای عبور از هرگونه محدودیت با حداکثر سرعت.' }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="arcane-card h-100">
                            <div class="icon-glow"><i class="fas fa-mask"></i></div>
                            <h4 class="fw-bold">{{ $settings->get('arcane_feature2_title') ?: 'پنهان‌سازی هویت' }}</h4>
                            <p class="text-secondary">{{ $settings->get('arcane_feature2_desc') ?: 'آی‌پی و موقعیت مکانی شما به طور کامل مخفی شده و ترافیک شما غیرقابل ردیابی خواهد بود.' }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="arcane-card h-100">
                            <div class="icon-glow"><i class="fas fa-infinity"></i></div>
                            <h4 class="fw-bold">{{ $settings->get('arcane_feature3_title') ?: 'اتصال بی‌پایان' }}</h4>
                            <p class="text-secondary">{{ $settings->get('arcane_feature3_desc') ?: 'سرورهای قدرتمند ما آپتایم ۹۹.۹٪ را تضمین می‌کنند تا هرگز اتصال خود را از دست ندهید.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-5">
            <div class="container">
                <div class="text-center mb-5" data-aos="fade-up">
                    <h2 class="section-title">{{ $settings->get('arcane_pricing_title') ?: 'انتخاب دسترسی' }}</h2>
                </div>
                <div class="row justify-content-center">
                    @forelse($plans as $plan)
                        <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="{{ $loop->iteration * 100 }}">
                            <div class="arcane-card h-100 d-flex flex-column {{ $plan->is_popular ? 'popular' : '' }}">
                                <div class="text-center mb-4">
                                    <h4 class="fw-bold">{{ $plan->name }}</h4>
                                    <p class="display-5 fw-bold">{{ number_format($plan->price) }}<span class="fs-6 text-secondary"> تومان</span></p>
                                    <hr style="border-color: var(--border-color);">
                                </div>
                                <ul class="list-unstyled my-4">
                                    <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: var(--glow-teal);"></i> {{ $plan->volume_gb }} گیگابایت ترافیک</li>
                                    <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: var(--glow-teal);"></i> {{ $plan->duration_days }} روز اعتبار</li>
                                </ul>
                                <div class="mt-auto text-center">
                                    <a href="{{ route('login') }}" class="btn-arcane w-100">فعالسازی</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center">در حال حاضر هیچ پلن فعالی وجود ندارد.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="py-5">
            <div class="container w-75">
                <div class="text-center mb-5" data-aos="fade-up">
                    <h2 class="section-title">{{ $settings->get('arcane_faq_title') ?: 'سوالات متداول' }}</h2>
                </div>
                <div class="accordion" id="faqAccordion" data-aos="fade-up" data-aos-delay="100">
                    <div class="arcane-card mb-3">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent text-light fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                {{ $settings->get('arcane_faq1_q') ?: 'آیا اطلاعات کاربران ذخیره می‌شود؟' }}
                            </button>
                        </h2>
                        <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary pt-3">
                                {{ $settings->get('arcane_faq1_a') ?: 'خیر. ما به حریم خصوصی شما متعهدیم و هیچ‌گونه گزارشی از فعالیت‌های شما (No-Log Policy) ذخیره نمی‌کنیم.' }}
                            </div>
                        </div>
                    </div>
                    <div class="arcane-card mb-3">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent text-light fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                {{ $settings->get('arcane_faq2_q') ?: 'چگونه می‌توانم سرویس را روی چند دستگاه استفاده کنم؟' }}
                            </button>
                        </h2>
                        <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary pt-3">
                                {{ $settings->get('arcane_faq2_a') ?: 'پس از خرید، یک کانفیگ دریافت می‌کنید که می‌توانید بر اساس پلن خود، آن را همزمان روی چند دستگاه (موبایل، کامپیوتر و...) استفاده کنید.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer py-4 mt-5">
            <div class="container text-center">
                <p class="mb-2 text-secondary small">{{ $settings->get('arcane_footer_text') ?: '© 2025 Arcane Networks' }}</p>
                <p class="mb-0 text-muted small" style="opacity: 0.7;">
                    طراحی و توسعه توسط
                    <a href="https://t.me/VPNMarket_OfficialSupport" target="_blank" rel="noopener noreferrer" class="fw-bold text-decoration-none" style="color: var(--text-secondary, #adb5bd);">
                        VPNMarket
                    </a>
                </p>
            </div>
        </footer>
    </div>
@endsection

@push('scripts')
    <!-- Particles.js Library -->
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <!-- Custom Particles Config -->
    <script src="{{ asset('themes/arcane/js/main.js') }}"></script>
@endpush
