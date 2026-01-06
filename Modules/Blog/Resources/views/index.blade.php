@extends('layouts.frontend')

@section('title', 'وبلاگ و مقالات آموزشی - دنیای اینترنت آزاد')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/rocket/css/style.css') }}">
    <style>

        body {
            background-color: #050505;
            background-image:
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            background-attachment: fixed;
            color: #fff;
        }
        .cyber-grid-bg {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px; pointer-events: none; z-index: -1;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 40%, rgba(0,0,0,0) 100%);
        }

        /* === استایل دکمه بازگشت خفن (جدید) === */
        .back-btn-floating {
            position: absolute;
            top: 30px;
            left: 30px;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 25px;
            background: rgba(255, 255, 255, 0.03); /* شیشه‌ای خیلی شفاف */
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            backdrop-filter: blur(10px); /* تار کردن پشت دکمه */
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-weight: 600;
            overflow: hidden;
        }


        .back-btn-floating::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        /* هاور دکمه (موس میره روش) */
        .back-btn-floating:hover {
            background: rgba(230, 74, 25, 0.15); /* رنگ نارنجی تم */
            border-color: #E64A19;
            color: #fff;
            box-shadow: 0 0 25px rgba(230, 74, 25, 0.4), inset 0 0 10px rgba(230, 74, 25, 0.2);
            transform: translateY(2px);
            padding-left: 35px; /* فضا باز میشه برای حرکت آیکون */
        }

        /* حرکت نور روی دکمه */
        .back-btn-floating:hover::before {
            left: 100%;
        }

        .back-btn-floating i {
            font-size: 1.1rem;
            transition: 0.3s;
        }

        .back-btn-floating:hover i {
            transform: translateX(-5px);
        }


        .blog-hero { position: relative; padding: 8rem 0 5rem; text-align: center; overflow: hidden; }
        .glow-effect { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 200px; height: 200px; background: linear-gradient(180deg, rgba(230, 74, 25, 0.4) 0%, rgba(124, 58, 237, 0.4) 100%); filter: blur(80px); border-radius: 50%; z-index: -1; animation: pulse-glow 6s infinite alternate; }
        @keyframes pulse-glow { 0% { transform: translate(-50%, -50%) scale(1); opacity: 0.6; } 100% { transform: translate(-50%, -50%) scale(1.5); opacity: 0.8; } }
        .blog-hero h1 { font-size: 3.5rem; font-weight: 900; margin-bottom: 1rem; background: -webkit-linear-gradient(45deg, #fff, #a5a5a5); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .blog-hero p { font-size: 1.2rem; color: #cfd4e0; max-width: 600px; margin: 0 auto; line-height: 1.8; }
        .blog-card { background: rgba(20, 20, 30, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; overflow: hidden; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); backdrop-filter: blur(10px); height: 100%; }
        .blog-card:hover { transform: translateY(-10px) scale(1.02); border-color: #E64A19; box-shadow: 0 20px 40px rgba(230, 74, 25, 0.2); }
        .blog-img-container { height: 240px; overflow: hidden; position: relative; }
        .blog-img-container img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease; }
        .blog-card:hover .blog-img-container img { transform: scale(1.15) rotate(2deg); }
        .blog-category-badge { position: absolute; top: 15px; right: 15px; background: rgba(230, 74, 25, 0.9); color: white; padding: 6px 14px; border-radius: 12px; font-size: 0.85rem; font-weight: 700; backdrop-filter: blur(4px); box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .blog-content-box { padding: 1.5rem; position: relative; }
        .blog-content-box::after { content: ''; position: absolute; bottom: 0; left: 0; width: 0%; height: 3px; background: linear-gradient(90deg, #E64A19, #ff9100); transition: width 0.4s ease; }
        .blog-card:hover .blog-content-box::after { width: 100%; }
        .hover-text-fire:hover { color: #E64A19 !important; transition: 0.3s; }
    </style>
@endpush

@section('content')

    <div class="cyber-grid-bg"></div>

    <!-- Hero Section -->
    <section class="blog-hero">


        <a href="{{ Auth::check() ? route('dashboard') : url('/') }}" class="back-btn-floating">
            <span>{{ Auth::check() ? 'بازگشت به داشبورد' : 'بازگشت به سایت' }}</span>
            <i class="fas fa-arrow-left"></i>
        </a>


        <div class="glow-effect"></div>
        <div class="container">
            <h1 data-aos="fade-down">وبلاگ و اخبار</h1>
            <p data-aos="fade-up" data-aos-delay="100">
                در دنیای اینترنت آزاد به‌روز باشید. جدیدترین مقالات آموزشی، ترفندها و اخبار امنیت دیجیتال.
            </p>
        </div>
    </section>

    <!-- Blog List Section -->
    <section class="pb-5">
        <div class="container">
            <div class="row g-4">
                @foreach($posts as $post)
                    <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                        <div class="blog-card d-flex flex-column">
                            <a href="{{ route('blog.show', $post->slug) }}" class="blog-img-container d-block">
                                @if($post->image)
                                    <img src="{{ asset('storage/' . $post->image) }}" alt="{{ $post->title }}">
                                @else
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-dark text-secondary">
                                        <i class="fas fa-image fa-3x opacity-25"></i>
                                    </div>
                                @endif
                                <span class="blog-category-badge">
                                    <i class="fas fa-tag me-1 text-xs"></i> {{ $post->category->name ?? 'عمومی' }}
                                </span>
                            </a>

                            <div class="blog-content-box text-end text-white flex-grow-1 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center small text-white-50 mb-3">
                                    <span><i class="far fa-eye ms-1"></i> {{ $post->view_count }}</span>
                                    <span><i class="far fa-calendar-alt ms-1"></i> {{ \Carbon\Carbon::parse($post->published_at)->format('Y/m/d') }}</span>
                                </div>

                                <h3 class="h5 font-weight-bold mb-3">
                                    <a href="{{ route('blog.show', $post->slug) }}" class="text-white text-decoration-none hover-text-fire">
                                        {{ $post->title }}
                                    </a>
                                </h3>

                                <p class="text-white-50 small mb-4 flex-grow-1" style="line-height: 1.7; opacity: 0.8;">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($post->content), 120) }}
                                </p>

                                <div class="mt-auto">
                                    <a href="{{ route('blog.show', $post->slug) }}" class="btn w-100 btn-outline-light rounded-pill border-opacity-25 hover-fire-btn">
                                        مطالعه مقاله <i class="fas fa-arrow-left ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-center mt-5">
                {{ $posts->links() }}
            </div>
        </div>
    </section>

@endsection
