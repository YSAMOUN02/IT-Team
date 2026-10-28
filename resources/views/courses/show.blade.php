@extends('layouts.site')

@section('title', $course->title)
@section('admin_edit', route('admin.courses.edit', $course))
{{-- (string): a null second argument would make Blade open a block section that never closes. --}}
@section('description', (string) $course->summary)
@if ($course->image)
    @section('og_image', url($course->mediaUrl()))
@endif

@push('schema')
    {!! \App\Support\Seo::jsonLd(['@context' => 'https://schema.org', '@graph' => [
        \App\Support\Seo::course($course),
        \App\Support\Seo::breadcrumbs([[__('site.courses'), route('courses.index')], [$course->title, $course->url()]]),
    ]]) !!}
@endpush

@php
    $lessonCount = $outline->sum(fn ($s) => $s['items']->filter(fn ($i) => $i instanceof \App\Models\CourseLesson)->count());
    $testCount = $outline->sum(fn ($s) => $s['items']->filter(fn ($i) => $i instanceof \App\Models\Quiz)->count());
    $moduleCount = $outline->filter(fn ($s) => $s['module'])->count();
@endphp

@section('content')
    <section class="course-hero">
        <div class="container course-hero-grid">
            <div>
                <p class="eyebrow"><a href="{{ route('courses.index') }}">{{ __('site.courses') }}</a></p>
                <h1>{{ $course->title }}</h1>
                @if ($course->summary)
                    <p class="lead">{{ $course->summary }}</p>
                @endif
                @if ($lessonCount || $testCount || $course->duration || $course->isFree())
                    <ul class="course-facts">
                        @if ($course->isFree())<li class="fact-free">{{ __('account.free') }}</li>@endif
                        @if ($course->duration)<li>⏱ {{ __('account.duration') }}: {{ $course->duration }}</li>@endif
                        @if ($moduleCount)<li>{{ trans_choice('account.modules_count', $moduleCount, ['count' => $moduleCount]) }}</li>@endif
                        @if ($lessonCount)<li>{{ trans_choice('account.lessons_count', $lessonCount, ['count' => $lessonCount]) }}</li>@endif
                        @if ($testCount)<li>{{ trans_choice('account.quizzes_count', $testCount, ['count' => $testCount]) }}</li>@endif
                        @if ($course->certificate_enabled && $lessonCount)<li>{{ __('account.certificate') }}</li>@endif
                    </ul>
                @endif
                @if ($course->features)
                    <h2 class="label-heading">{{ __('site.description') }}</h2>
                    @include('partials.feature-groups', ['course' => $course])
                @endif
            </div>

            <aside class="price-card" id="buy">
                @if (session('checkout_notice'))
                    <div class="alert alert-info" role="status">{{ session('checkout_notice') }}</div>
                @endif
                @if ($course->icon)
                    <img class="course-icon" src="{{ $course->mediaUrl('icon') }}" alt="" width="48" height="48">
                @endif
                @if ($course->isFree())
                    <span class="price price-free">{{ __('account.free') }}</span>
                    <span class="price-suffix">{{ __('account.access_lifetime') }}</span>
                @elseif ($course->plans->isEmpty() && $course->price)
                    {{-- Old price text, for courses that are not sold online --}}
                    @if ($course->price_prefix)<span class="price-prefix">{{ $course->price_prefix }}</span>@endif
                    <span class="price">{{ $course->price }}</span>
                    @if ($course->price_suffix)<span class="price-suffix">{{ $course->price_suffix }}</span>@endif
                @endif
                @if ($course->badge)
                    <p class="price-meta">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        {{ $course->badge }} {{ __('site.learners') }}
                    </p>
                @endif

                @if ($hasAccess)
                    <p class="access-note">
                        {{ $enrollment?->expires_at ? __('account.enrolled_until', ['date' => $enrollment->expires_at->format('d/m/Y')]) : __('account.enrolled_lifetime') }}
                    </p>
                    <a class="btn btn-primary btn-block" href="{{ route('learn.course', $course->slug) }}">{{ __('account.go_to_course') }}</a>
                    @if ($canBuy && $enrollment?->expires_at)
                        <a class="btn btn-outline btn-block" href="{{ route('checkout.show', [$course->slug, 'renew' => 1]) }}">{{ __('account.renew') }}</a>
                    @endif
                @elseif ($course->isFree())
                    <a class="btn btn-primary btn-block btn-lg" href="{{ route('courses.enroll', $course->slug) }}">{{ __('account.enroll_free') }}</a>
                    <p class="access-note">{{ auth()->check() ? __('account.enroll_free_note') : __('account.enroll_free_guest') }}</p>
                @elseif ($canBuy)
                    <form method="get" action="{{ route('checkout.show', $course->slug) }}" class="plan-picker">
                        @foreach ($course->plans as $plan)
                            <label class="plan-option">
                                <input type="radio" name="plan" value="{{ $plan->id }}" @checked($loop->first)>
                                <span class="plan-box">
                                    <span class="plan-period">
                                        {{ $plan->periodLabel() }}
                                        @if ($plan->discountActive())
                                            <b class="disc">−{{ $plan->discount_percent }}%</b>
                                        @endif
                                    </span>
                                    <span class="plan-price">
                                        @if ($plan->discountActive())<s>{{ $plan->formattedOriginalPrice() }}</s>@endif
                                        <strong>{{ $plan->formattedPrice() }}</strong>
                                    </span>
                                    @if ($plan->discountActive() && $plan->discount_ends_at)
                                        <small class="plan-until">{{ __('account.discount_until', ['date' => $plan->discount_ends_at->format('d/m/Y')]) }}</small>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                        <button class="btn btn-primary btn-block btn-lg">{{ __('account.buy_now') }}</button>
                    </form>
                    <p class="pay-badges" aria-label="{{ __('account.payment_method') }}">
                        <span>VISA</span><span>Mastercard</span><span>UnionPay</span><span>ABA KHQR</span>
                    </p>
                    <a class="btn btn-outline btn-block" href="{{ $course->cta_url ? link_url($course->cta_url) : '#contact-form' }}">{{ $course->cta_label ?: __('site.contact_us') }}</a>
                @else
                    <a class="btn btn-primary btn-block" href="{{ $course->cta_url ? link_url($course->cta_url) : '#contact-form' }}">{{ $course->cta_label ?: __('site.contact_us') }}</a>
                @endif
            </aside>
        </div>
    </section>

    @if ($outline->isNotEmpty())
        <section class="section">
            <div class="container narrow">
                <h2>{{ __('account.course_content') }}</h2>
                @include('partials.curriculum', ['outline' => $outline, 'course' => $course, 'hasAccess' => $hasAccess])
            </div>
        </section>
    @endif

    <section @class(['section', 'section-flush-top' => $outline->isNotEmpty()])>
        <div class="container narrow">
            @if ($course->image)
                <img class="featured-image" src="{{ $course->mediaUrl() }}" alt="{{ $course->title }}">
            @endif
            <h2>{{ __('site.about_course', ['title' => $course->title]) }}</h2>
            <div class="prose entry">{!! $course->body !!}</div>
        </div>
    </section>

    <section class="section section-tint">
        <div class="container split split-form">
            <div class="split-copy">
                <h2>{{ __('site.interested') }}</h2>
                @php
                    $contactPage = \App\Models\Page::findBySlug('contact');
                @endphp
                @if ($contactPage?->field('intro.text'))
                    <div class="prose">{!! rich_text($contactPage->field('intro.text')) !!}</div>
                @endif
            </div>
            <div class="card form-card">
                @include('partials.contact-form', ['subject' => $course->title])
            </div>
        </div>
    </section>

    @if ($others->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head">
                    <h2>{{ __('site.our_courses') }}</h2>
                    <a class="btn btn-outline" href="{{ route('courses.index') }}">{{ __('site.all_courses') }}</a>
                </div>
                <div class="grid grid-3">
                    @foreach ($others as $course)
                        @include('partials.course-card')
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
