@extends('layouts.site')

@section('title', $page->title && $page->title !== setting('site_name') ? $page->title : (string) $page->field('hero.title'))
@section('description', $page->meta_description ?: strip_tags((string) $page->field('hero.text')))
@if ($page->exists)
    @section('admin_edit', route('admin.pages.edit', $page))
@endif
@if ($page->field('hero.image'))
    @section('og_image', url(media_url($page->field('hero.image'))))
@endif
@section('body_class', 'page-home')

@section('content')
    {{-- Hero: a white card with the title and buttons, overlapping a large photo on the right --}}
    <section class="shero">
        <div class="container shero-inner">
            @if ($page->field('hero.image'))
                <div class="shero-photo">
                    <img src="{{ media_url($page->field('hero.image')) }}" alt="{{ $page->field('hero.title') }}" fetchpriority="high">
                </div>
            @endif
            <div class="shero-card">
                <h1>{{ $page->field('hero.title', setting('site_name')) }}</h1>
                @if ($page->field('hero.text'))
                    <div class="shero-text">{!! rich_text($page->field('hero.text')) !!}</div>
                @endif
                <div class="shero-actions">
                    @if ($page->field('hero.button_label'))
                        <a class="btn btn-primary" href="{{ link_url($page->field('hero.button_url')) }}">{{ $page->field('hero.button_label') }}</a>
                    @endif
                    @if ($page->field('hero.button2_label'))
                        <a class="btn btn-outline" href="{{ link_url($page->field('hero.button2_url')) }}">{{ $page->field('hero.button2_label') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @include('partials.ad', ['placement' => 'header', 'class' => 'container ad-below-hero'])

    {{-- Courses --}}
    @if ($courses->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head">
                    <div>
                        <h2>{{ $page->field('courses.title', __('site.our_courses')) }}</h2>
                        @if ($page->field('courses.text'))
                            <p class="section-text">{{ $page->field('courses.text') }}</p>
                        @endif
                    </div>
                    <a class="btn btn-outline" href="{{ link_url($page->field('courses.button_url', route('courses.index'))) }}">{{ $page->field('courses.button_label', __('site.all_courses')) }}</a>
                </div>
                <div class="grid grid-3">
                    @foreach ($courses as $course)
                        @include('partials.course-card')
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Lessons --}}
    @if ($lessons->isNotEmpty())
        <section class="section section-tint">
            <div class="container">
                <div class="section-head">
                    <div>
                        <h2>{{ $page->field('lessons.title', __('site.our_lessons')) }}</h2>
                        @if ($page->field('lessons.text'))
                            <p class="section-text">{{ $page->field('lessons.text') }}</p>
                        @endif
                    </div>
                    <a class="btn btn-outline" href="{{ link_url($page->field('lessons.button_url', route('tutorials.index'))) }}">{{ $page->field('lessons.button_label', __('site.more_lessons')) }}</a>
                </div>
                <div class="grid grid-3">
                    @foreach ($lessons as $article)
                        @include('partials.article-card')
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @include('partials.ad', ['placement' => 'home_middle', 'class' => 'container'])

    {{-- Latest news --}}
    @if ($news->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head">
                    <div>
                        <h2>{{ $page->field('news.title', __('site.latest_news')) }}</h2>
                        @if ($page->field('news.text'))
                            <p class="section-text">{{ $page->field('news.text') }}</p>
                        @endif
                    </div>
                    <a class="btn btn-outline" href="{{ route('news.index') }}">{{ __('site.view_all') }}</a>
                </div>
                <div class="grid grid-3">
                    @foreach ($news as $article)
                        @include('partials.article-card', ['showExcerpt' => true])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Introduction --}}
    @if ($page->field('intro.title'))
        <section class="section">
            <div class="container split">
                <div class="split-copy">
                    <h2>{{ $page->field('intro.title') }}</h2>
                    @if ($page->field('intro.text'))
                        <div class="prose">{!! rich_text($page->field('intro.text')) !!}</div>
                    @endif
                    @include('partials.checklist', ['items' => $page->field('intro.points', [])])
                    @if ($page->field('intro.after'))
                        <div class="prose">{!! rich_text($page->field('intro.after')) !!}</div>
                    @endif
                    @if ($page->field('intro.button_label'))
                        <a class="btn btn-primary" href="{{ link_url($page->field('intro.button_url')) }}">{{ $page->field('intro.button_label') }}</a>
                    @endif
                </div>
                @if ($page->field('intro.image'))
                    <div class="split-media">
                        <img src="{{ media_url($page->field('intro.image')) }}" alt="" loading="lazy">
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Corporate training --}}
    @if ($page->field('corporate.title'))
        <section class="section section-tint">
            <div class="container split split-reverse">
                <div class="split-copy">
                    <h2>{{ $page->field('corporate.title') }}</h2>
                    @if ($page->field('corporate.text'))
                        <div class="prose">{!! rich_text($page->field('corporate.text')) !!}</div>
                    @endif
                    @include('partials.checklist', ['items' => $page->field('corporate.points', [])])
                    @if ($page->field('corporate.button_label'))
                        <a class="btn btn-primary" href="{{ link_url($page->field('corporate.button_url')) }}">{{ $page->field('corporate.button_label') }}</a>
                    @endif
                </div>
                @if ($page->field('corporate.image'))
                    <div class="split-media split-media-plain">
                        <img src="{{ media_url($page->field('corporate.image')) }}" alt="" loading="lazy">
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Testimonials --}}
    @if ($testimonials->isNotEmpty())
        <section class="section" id="testimonials">
            <div class="container">
                <div class="section-head section-head-center">
                    <h2>{{ $page->field('testimonials.title', 'Testimonials') }}</h2>
                    @if ($page->field('testimonials.text'))
                        <p class="section-text">{{ $page->field('testimonials.text') }}</p>
                    @endif
                </div>
                <div class="grid grid-3">
                    @foreach ($testimonials as $testimonial)
                        <figure class="card testimonial">
                            <div class="stars" aria-label="{{ $testimonial->rating }}/5">
                                @for ($i = 1; $i <= 5; $i++)
                                    <svg viewBox="0 0 24 24" @class(['off' => $i > $testimonial->rating]) aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9L12 3Z"/></svg>
                                @endfor
                            </div>
                            <blockquote>{!! nl2br(e($testimonial->quote)) !!}</blockquote>
                            <figcaption>
                                @if ($testimonial->photo)
                                    <img src="{{ $testimonial->mediaUrl('photo') }}" alt="" loading="lazy">
                                @else
                                    <span class="avatar-fallback">{{ mb_substr($testimonial->name, 0, 1) }}</span>
                                @endif
                                <span>
                                    <strong>{{ $testimonial->name }}</strong>
                                    @if ($testimonial->role)
                                        <small>{{ $testimonial->role }}</small>
                                    @endif
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Partners --}}
    @if ($partners->isNotEmpty())
        <section class="section section-tint section-tight">
            <div class="container">
                <h2 class="partners-title">{{ $page->field('partners.title', 'Partners') }}</h2>
                <ul class="partners">
                    @foreach ($partners as $partner)
                        <li>
                            @if ($partner->url)<a href="{{ $partner->url }}" target="_blank" rel="noopener" title="{{ $partner->name }}">@endif
                            @if ($partner->logo)
                                <img src="{{ $partner->mediaUrl('logo') }}" alt="{{ $partner->name }}" loading="lazy">
                            @else
                                <span>{{ $partner->name }}</span>
                            @endif
                            @if ($partner->url)</a>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Founder --}}
    @if ($page->field('founder.title'))
        <section class="section section-dark">
            <div class="container split split-reverse founder">
                <div class="split-copy">
                    <h2>{{ $page->field('founder.title') }}</h2>
                    <div class="prose">{!! rich_text($page->field('founder.text')) !!}</div>
                    <a class="btn btn-light" href="{{ route('about') }}">{{ __('site.read_more') }}</a>
                </div>
                @if ($page->field('founder.image'))
                    <div class="split-media founder-photo">
                        <img src="{{ media_url($page->field('founder.image')) }}" alt="{{ $page->field('founder.title') }}" loading="lazy">
                    </div>
                @endif
            </div>
        </section>
    @endif
@endsection
