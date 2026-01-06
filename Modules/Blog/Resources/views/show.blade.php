@extends('layouts.frontend')

@section('title', $post->seo_title ?? $post->title)

@section('meta_tags')
    <meta name="description" content="{{ $post->seo_description ?? Str::limit(strip_tags($post->content), 150) }}">
    <meta name="keywords" content="{{ $post->seo_keywords }}">
    <meta property="og:title" content="{{ $post->seo_title ?? $post->title }}" />
    <meta property="og:description" content="{{ $post->seo_description ?? Str::limit(strip_tags($post->content), 150) }}" />
    @if($post->image)
        <meta property="og:image" content="{{ asset('storage/' . $post->image) }}" />
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/rocket/css/style.css') }}">
    <style>
        .blog-content { color: #e0e0e0; line-height: 1.8; text-align: justify; }
        .blog-content h2, .blog-content h3 { color: #E64A19; margin-top: 2rem; margin-bottom: 1rem; }
        .blog-content p { margin-bottom: 1.5rem; }
        .blog-content img { max-width: 100%; border-radius: 12px; margin: 20px 0; }
        .author-box { background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); }
    </style>
@endpush

@section('content')

    <section class="hero" style="height: 50vh; min-height: 350px;">
        <div class="container text-center">
            <h1 class="display-5 fw-bold">{{ $post->title }}</h1>
            <div class="mt-3 text-white-50">
                <span class="mx-2"><i class="far fa-user ms-1"></i> {{ $post->author->name ?? 'ادمین' }}</span>
                <span class="mx-2"><i class="far fa-calendar-alt ms-1"></i> {{ \Carbon\Carbon::parse($post->published_at)->format('Y/m/d') }}</span>
                <span class="mx-2"><i class="far fa-folder ms-1"></i> {{ $post->category->name ?? 'عمومی' }}</span>
            </div>
        </div>
    </section>

    <section class="py-5 bg-darker">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="bg-dark p-4 p-md-5 rounded-4 border border-secondary border-opacity-25 shadow-lg">

                        @if($post->image)
                            <img src="{{ asset('storage/' . $post->image) }}" alt="{{ $post->title }}" class="w-100 rounded-3 mb-5 shadow-sm">
                        @endif

                        <div class="blog-content">
                            {!! $post->content !!}
                        </div>

                        <hr class="border-secondary my-5">

                        {{-- دکمه بازگشت --}}
                        <div class="text-center">
                            <a href="{{ route('blog.index') }}" class="btn btn-outline-light rounded-pill px-4">
                                <i class="fas fa-arrow-right ms-2"></i> بازگشت به وبلاگ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
