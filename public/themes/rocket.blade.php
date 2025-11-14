
@extends('layouts.frontend')

@section('title', 'RoketVPN - پلن‌های اشتراک')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/rocket/css/style.css') }}">
    <style>
        /* ==== Plan Filters ==== */
        .plan-filters {
            display: flex;
            justify-content: center;
            gap: 0.8rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
            padding: 0 1rem;
        }
        .filter-btn {
            padding: 0.55rem 1.4rem;
            border: 1px solid var(--fire);
            background: var(--glass-bg);
            color: var(--gray);
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .filter-btn.active,
        .filter-btn:hover {
            background: var(--fire);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255,60,0,0.3);
        }

        /* ==== Plan Cards ==== */
        .plan-card {
            transition: all 0.4s ease;
            opacity: 1;
            transform: translateY(0);
        }
        .plan-card.hidden {
            opacity: 0;
            transform: translateY(20px);
            height: 0;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        .pricing-card {
            background: var(--glass-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem 1.5rem;
            height: 100%;
            text-align: right;
            direction: rtl;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .pricing-card.popular {
            border: 2px solid var(--fire);
            box-shadow: 0 0 25px rgba(255,60,0,0.2);
            transform: scale(1.03);
        }
        .pricing-card h4 {
            font-weight: 700;
            margin-bottom: 0.8rem;
            color: var(--white);
        }
        .duration-badge {
            display: inline-block;
            background: var(--fire);
            color: var(--white);
            padding: 0.35rem 0.9rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }
        .price {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--fire);
            margin: 0.8rem 0;
        }
        .price small {
            font-size: 0.9rem;
            color: var(--gray);
        }
        .monthly-price {
            font-size: 1rem;
            color: #4CAF50;
            font-weight: 600;
            margin: 0.5rem 0;
        }
        .pricing-card ul {
            list-style: none;
            padding-right: 0;
            color: var(--gray);
            margin-top: 1rem;
        }
        .pricing-card ul li {
            margin-bottom: 0.6rem;
        }
        .pricing-card ul li i {
            color: #4CAF50;
            margin-left: 0.5rem;
            margin-right: 0;
        }

        /* ==== Buttons ==== */
        .btn-fire {
            background: var(--fire);
            color: var(--white);
            font-weight: 700;
            padding: 12px 35px;
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(255,60,0,0.4);
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn-fire:hover {
            background: #ff5722;
            transform: translateY(-3px);
        }
    </style>
@endpush

@section('content')
    <!-- Hero -->
    <section class="hero">
        <div class="container text-center">
            <h1>🔥 سرعت و امنیت در اوج</h1>
            <p>با RoketVPN دنیای اینترنت را آزادانه و امن تجربه کنید.</p>
            <a href="#pricing" class="btn-fire">انتخاب پلن</a>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-5">
        <div class="container">
            <h2 class="section-title text-center mb-5">انتخاب مدت زمان اشتراک</h2>

            <!-- Plan Filters -->
            <div class="plan-filters">
                <button class="filter-btn active" data-filter="all">همه پلن‌ها</button>
                @php
                    $durations = $plans->pluck('duration_label')->unique()->sort();
                @endphp
                @foreach($durations as $duration)
                    <button class="filter-btn" data-filter="{{ $duration }}">{{ $duration }}</button>
                @endforeach
            </div>

            <!-- Plan Cards -->
            <div class="row justify-content-center">
                @foreach($plans as $plan)
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4 plan-card" data-category="{{ $plan->duration_label }}">
                        <div class="pricing-card {{ $plan->is_popular ? 'popular' : '' }}">
                            @if($plan->is_popular)
                                <span class="badge bg-warning position-absolute top-0 start-50 translate-middle-x px-3 py-1">محبوب</span>
                            @endif
                            <h4>{{ $plan->name }}</h4>
                            <div class="duration-badge">{{ $plan->duration_label }}</div>
                            <div class="price">
                                {{ number_format($plan->price) }} <small>تومان</small>
                            </div>
                            @if($plan->duration_days > 30)
                                <div class="monthly-price">{{ number_format($plan->monthly_price) }} تومان/ماه</div>
                            @endif
                            <ul>
                                @foreach(explode("\n", $plan->features) as $feature)
                                    @if(trim($feature))
                                        <li><i class="fas fa-check"></i>{{ trim($feature) }}</li>
                                    @endif
                                @endforeach
                            </ul>
                            <form method="POST" action="{{ route('order.store', $plan->id) }}" class="mt-4">
                                @csrf
                                <button type="submit" class="btn-fire py-2">خرید {{ $plan->duration_label }}</button>
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
            <h2 class="section-title text-center mb-5">سوالات متداول</h2>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q1">
                            آیا اطلاعات من ذخیره می‌شود؟
                        </button>
                    </h2>
                    <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">خیر. RoketVPN هیچ اطلاعاتی از کاربران ذخیره نمی‌کند. سیاست ما: No-Log.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q2">
                            روی چند دستگاه می‌توانم استفاده کنم؟
                        </button>
                    </h2>
                    <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">بسته به پلن خریداری‌شده، می‌توانید تا ۵ دستگاه را هم‌زمان متصل کنید.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-4 text-center">
        <div class="container">
            <p>© 2025 <span>RoketVPN</span> — همه حقوق محفوظ است.</p>
            <div class="social-links mt-3">
                <a href="https://t.me/V2_ATASH" target="_blank" class="social-link telegram">
                    <i class="fab fa-telegram-plane"></i>
                </a>
                <a href="https://www.instagram.com/v2_atash/" target="_blank" class="social-link instagram">
                    <i class="fab fa-instagram"></i>
                </a>
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
                        if(filter === "all" || category === filter){
                            card.classList.remove("hidden");
                            setTimeout(() => card.style.opacity = 1, 10);
                        } else {
                            card.style.opacity = 0;
                            setTimeout(() => card.classList.add("hidden"), 300);
                        }
                    });
                });
            });

            planCards.forEach((card, i) => {
                card.style.transitionDelay = `${i * 80}ms`;
            });
        });
    </script>
@endpush
