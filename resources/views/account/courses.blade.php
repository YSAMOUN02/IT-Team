@extends('layouts.site')

@section('title', __('account.my_courses'))
@section('no_ads', true)

@php
    $firstName = \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ') ?: auth()->user()->name;
@endphp

@section('content')
    <section class="account-head learn-hero">
        <div class="container">
            <p class="eyebrow">{{ __('account.hello', ['name' => $firstName]) }}</p>
            <h1>{{ __('account.my_courses') }}</h1>
            @if ($enrollments->isNotEmpty())
                <ul class="learn-stats">
                    <li><strong data-count="{{ $stats['courses'] }}">{{ $stats['courses'] }}</strong><span>{{ __('account.stat_courses') }}</span></li>
                    <li><strong data-count="{{ $stats['lessons'] }}">{{ $stats['lessons'] }}</strong><span>{{ __('account.stat_lessons_done') }}</span></li>
                    <li><strong data-count="{{ $stats['completed'] }}">{{ $stats['completed'] }}</strong><span>{{ __('account.stat_completed') }}</span></li>
                    <li><strong data-count="{{ $stats['certificates'] }}">{{ $stats['certificates'] }}</strong><span>{{ __('account.stat_certificates') }}</span></li>
                </ul>
            @endif
            @include('account._tabs')
        </div>
    </section>

    <section class="section section-flush-top">
        <div class="container">
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            @if ($pendingOrders->isNotEmpty())
                <div class="alert alert-info">
                    <strong>{{ __('account.pending_orders') }}:</strong>
                    @foreach ($pendingOrders as $order)
                        <a href="{{ route('orders.show', $order) }}">{{ $order->course_title }} ({{ $order->number }})</a>@if (! $loop->last), @endif
                    @endforeach
                </div>
            @endif

            @if ($enrollments->isEmpty())
                <div class="empty-box">
                    <p>{{ __('account.my_courses_empty') }}</p>
                    <a class="btn btn-primary" href="{{ route('courses.index') }}">{{ __('account.browse_courses') }}</a>
                </div>
            @else
                @if ($featured)
                    @php
                        ['course' => $fCourse, 'progress' => $fProgress, 'next' => $fNext, 'lastActive' => $fLast] = $featured;
                    @endphp
                    <article class="card continue-card">
                        <a class="continue-thumb" href="{{ route('learn.course', $fCourse->slug) }}" tabindex="-1" aria-hidden="true">
                            @if ($fCourse->image)
                                <img src="{{ $fCourse->mediaUrl() }}" alt="">
                            @endif
                        </a>
                        <div class="continue-body">
                            <p class="eyebrow">{{ __('account.pick_up') }}</p>
                            <h2><a href="{{ route('learn.course', $fCourse->slug) }}">{{ $fCourse->title }}</a></h2>
                            <div class="progress progress-animated" role="progressbar" aria-valuenow="{{ $fProgress->percent }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $fProgress->percent }}%"></span></div>
                            <p class="my-course-meta">
                                <span>{{ __('account.progress', ['percent' => $fProgress->percent]) }} · {{ $fProgress->done }}/{{ $fProgress->total }}</span>
                                @if ($fLast)<span>{{ __('account.last_studied', ['time' => $fLast->diffForHumans()]) }}</span>@endif
                            </p>
                            <a class="next-up" href="{{ \App\Support\CourseProgress::urlFor($fCourse, $fNext) }}">
                                <span class="next-icon" aria-hidden="true">
                                    @if ($fNext instanceof \App\Models\Quiz)
                                        <svg viewBox="0 0 24 24"><path d="M9 4h6l1 2h3v14H5V6h3z"/><path d="m9 13 2 2 4-4"/></svg>
                                    @else
                                        <svg viewBox="0 0 24 24"><path d="m8 5 11 7-11 7z"/></svg>
                                    @endif
                                </span>
                                <span class="next-text">
                                    <small>{{ __('account.next_up') }}</small>
                                    <strong>{{ $fNext->title }}</strong>
                                </span>
                                <span class="next-arrow" aria-hidden="true">→</span>
                            </a>
                        </div>
                        <div class="ring ring-animated continue-ring" style="--p: {{ $fProgress->percent }}" aria-hidden="true">
                            <span><b data-count="{{ $fProgress->percent }}">{{ $fProgress->percent }}</b>%</span>
                        </div>
                    </article>
                @endif

                <div class="grid grid-3 my-courses">
                    @foreach ($enrollments as ['enrollment' => $enrollment, 'course' => $course, 'progress' => $progress, 'next' => $next])
                        @php
                            $active = $enrollment->isActive();
                            $daysLeft = $active && $enrollment->expires_at ? (int) ceil(now()->diffInDays($enrollment->expires_at, false)) : null;
                            [$badge, $badgeClass] = match (true) {
                                (bool) $enrollment->completed_at || $progress->complete => [__('account.completed'), 'is-done'],
                                ! $active => [__('account.expired'), 'is-expired'],
                                $daysLeft !== null && $daysLeft <= 7 => [__('account.days_left', ['count' => max(0, $daysLeft)]), 'is-soon'],
                                $progress->done === 0 => [__('account.not_started'), 'is-new'],
                                default => [__('account.in_progress'), 'is-going'],
                            };
                        @endphp
                        <article @class(['card', 'my-course', 'is-expired' => ! $active])>
                            <a class="article-thumb" href="{{ $active ? route('learn.course', $course->slug) : $course->url() }}" tabindex="-1" aria-hidden="true">
                                @if ($course->image)
                                    <img src="{{ $course->mediaUrl() }}" alt="">
                                @else
                                    <span class="thumb-placeholder"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/></svg></span>
                                @endif
                                <span class="my-course-badge {{ $badgeClass }}">{{ $badge }}</span>
                            </a>
                            <div class="article-card-body">
                                <h3 class="card-title"><a href="{{ $active ? route('learn.course', $course->slug) : $course->url() }}">{{ $course->title }}</a></h3>
                                <div class="progress progress-animated" role="progressbar" aria-valuenow="{{ $progress->percent }}" aria-valuemin="0" aria-valuemax="100">
                                    <span style="width: {{ $progress->percent }}%"></span>
                                </div>
                                <p class="my-course-meta">
                                    <span>{{ $progress->percent }}% · {{ $progress->done }}/{{ $progress->total }}</span>
                                    <span>
                                        @if (! $active)
                                            {{ __('account.expired') }}
                                        @elseif ($enrollment->expires_at)
                                            {{ __('account.expires', ['date' => $enrollment->expires_at->format('d/m/Y')]) }}
                                        @else
                                            {{ __('account.access_lifetime') }}
                                        @endif
                                    </span>
                                </p>
                                @if ($next)
                                    <p class="my-course-next"><small>{{ __('account.next_up') }}:</small> {{ $next->title }}</p>
                                @endif
                                <div class="btn-row">
                                    @if ($active)
                                        <a class="btn btn-primary btn-sm" href="{{ $next ? \App\Support\CourseProgress::urlFor($course, $next) : route('learn.course', $course->slug) }}">{{ $progress->done ? __('account.continue') : __('account.start') }}</a>
                                    @elseif ($course->isPurchasable())
                                        <a class="btn btn-primary btn-sm" href="{{ route('checkout.show', [$course->slug, 'renew' => 1]) }}">{{ __('account.renew') }}</a>
                                    @endif
                                    @if ($enrollment->certificate_code)
                                        <a class="btn btn-outline btn-sm" href="{{ route('learn.certificate', $course->slug) }}">{{ __('account.view_certificate') }}</a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
