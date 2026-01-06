{{-- resources/views/frontend/plans.blade.php --}}
@extends('layouts.frontend')

@section('title', (setting('rocket_navbar_brand', 'RoketVPN') . ' - پلن‌های اشتراک'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/rocket/css/style.css') }}">
    <style>
        .plan-filters { display: flex; justify-content: center; gap: 0.8rem; margin-bottom: 2.5rem; flex-wrap: wrap; padding: 0 1rem; }
        .filter-btn { padding: 0.55rem 1.3rem; border: 2px solid #E64A19; background: transparent; color: #E64A19; border-radius: 50px; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer; white-space: nowrap; }
        .filter-btn.active, .filter-btn:hover { background: #E64A19; color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(230, 74, 25, 0.3); }
        .plan-card { transition: all 0.4s ease; opacity: 1; transform: translateY(0); }
        .plan-card.hidden { opacity: 0; transform: translateY(20px); height: 0; margin: 0; padding: 0; overflow: hidden; }
        .pricing-card { background: #1a1a1a; border: 1px solid #333; border-radius: 16px; padding: 1.8rem; height: 100%; position: relative; transition: all 0.3s ease; }
        .pricing-card.popular { border-color: #E64A19; box-shadow: 0 0 25px rgba(230, 74, 25, 0.2); transform: scale(1.03); }
        .price { font-size: 2.1rem; font-weight: 900; color: #E64A19; margin: 1rem 0; }
        .price small { font-size: 0.9rem; color: #999; }
        .monthly-price { font-size: 1rem; color: #4CAF50; font-weight: 600; margin: 0.5rem 0; }
        .duration-badge { display: inline-block; background: #E64A19; color: white; padding: 0.35rem 0.9rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; margin: 0.5rem 0; }
    </style>
@endpush

@section('content')

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">{{ setting('rocket_navbar_brand', 'RoketVPN') }}</a>
            <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div id="nav" class="collapse navbar-collapse">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#features">ویژگی‌ها</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">پلن‌ها</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">سوالات</a></li>

                    @if(\Nwidart\Modules\Facades\Module::isEnabled('Blog'))
                        <li class="nav-item"><a class="nav-link" href="{{ route('blog.index') }}">وبلاگ</a></li>
                    @endif

                </ul>
                <a href="{{ route('login') }}" class="btn btn-fire btn-sm">ورود / ثبت‌نام</a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="container text-center">
            <h1>{{ setting('rocket_hero_title', 'آتش سرعت و امنیت') }}</h1>
            <p>{{ setting('rocket_hero_subtitle', 'با RoketVPN وارد دنیایی شوید که سرعت، امنیت و آزادی در اوج هماهنگی‌اند.') }}</p>
            <a href="#pricing" class="btn-fire">{{ setting('rocket_hero_button_text', 'انتخاب پلن') }}</a>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-5">
        <div class="container">
            <h2 class="section-title text-center mb-4">{{ setting('rocket_pricing_title', 'انتخاب مدت زمان اشتراک') }}</h2>

            <div class="plan-filters">
                <button class="filter-btn active" data-filter="all">همه پلن‌ها</button>
                @php
                    $durations = $plans->pluck('duration_label')->unique()->sort(function ($a, $b) {
                        $order = ['۱ ماهه' => 1, '۲ ماهه' => 2, '۳ ماهه' => 3, '۱ ساله' => 4];
                        return ($order[$a] ?? 99) <=> ($order[$b] ?? 99);
                    });
                @endphp
                @foreach($durations as $duration)
                    <button class="filter-btn" data-filter="{{ $duration }}">{{ $duration }}</button>
                @endforeach
            </div>

            <div class="row justify-content-center">
                @foreach($plans->sortBy(function ($plan) {
                    $order = ['۱ ماهه' => 1, '۲ ماهه' => 2, '۳ ماهه' => 3, '۱ ساله' => 4];
                    return $order[$plan->duration_label] ?? 99;
                }) as $plan)
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4 plan-card" data-category="{{ $plan->duration_label }}">
                        <div class="pricing-card text-center {{ $plan->is_popular ? 'popular' : '' }}">
                            @if($plan->is_popular)
                                <span class="badge bg-warning position-absolute top-0 start-50 translate-middle-x px-3 py-1">محبوب</span>
                            @endif
                            <h4 class="mt-4">{{ $plan->name }}</h4>
                            <div class="duration-badge">{{ $plan->duration_label }}</div>
                            <div class="price">{{ number_format($plan->price) }}<small> تومان</small></div>
                            @if($plan->duration_days > 30)
                                <div class="monthly-price">{{ number_format($plan->monthly_price) }} تومان/ماه</div>
                            @endif
                            <ul class="list-unstyled mt-3 small text-end">
                                @foreach(explode("\n", $plan->features) as $feature)
                                    @if(trim($feature))
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>{{ trim($feature) }}</li>
                                    @endif
                                @endforeach
                            </ul>
                            <form method="POST" action="{{ route('order.store', $plan->id) }}" class="mt-4">
                                @csrf
                                <button type="submit" class="btn-fire w-100 py-2">خرید {{ $plan->duration_label }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-5">
        <div class="container w-75">
            <h2 class="section-title text-center mb-5">{{ setting('rocket_faq_title', 'سوالات متداول') }}</h2>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q1">
                            {{ setting('rocket_faq1_q', 'آیا اطلاعات من ذخیره می‌شود؟') }}
                        </button>
                    </h2>
                    <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            {{ setting('rocket_faq1_a', 'خیر. RoketVPN هیچ اطلاعاتی از کاربران ذخیره نمی‌کند. سیاست ما: No-Log.') }}
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q2">
                            {{ setting('rocket_faq2_q', 'روی چند دستگاه می‌توانم استفاده کنم؟') }}
                        </button>
                    </h2>
                    <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            {{ setting('rocket_faq2_a', 'بسته به پلن خریداری‌شده، می‌توانید تا ۵ دستگاه را هم‌زمان متصل کنید.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-4 text-center">
        <div class="container">
            <p>{!! setting('rocket_footer_text', '© 2025 <span>RoketVPN</span> — همه حقوق محفوظ است.') !!}</p>
            <div class="social-links mt-3">
                <a href="{{ setting('telegram_link', '#') }}" target="_blank" class="social-link telegram"><i class="fab fa-telegram-plane"></i></a>
                <a href="{{ setting('instagram_link', '#') }}" target="_blank" class="social-link instagram"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </footer>

@endsection

@push('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const filterButtons = document.querySelectorAll(".filter-btn");
            const planCards = document.querySelectorAll(".plan-card");

            filterButtons.forEach(btn => {
                btn.addEventListener("click", () => {
                    filterButtons.forEach(b => b.classList.remove("active"));
                    btn.classList.add("active");
                    const filter = btn.dataset.filter;

                    planCards.forEach(card => {
                        const category = card.dataset.category;
                        if (filter === "all" || category === filter) {
                            card.classList.remove("hidden");
                        } else {
                            card.classList.add("hidden");
                        }
                    });
                });
            });
        });
    </script>
@endpush
