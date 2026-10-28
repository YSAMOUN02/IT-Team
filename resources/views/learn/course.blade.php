@extends('learn._layout')

@section('title', $course->title)
@section('admin_edit', route('admin.courses.curriculum', $course))

@php
    $testsDone = $progress->done - count($progress->completedLessonIds);
    $daysLeft = $enrollment?->expires_at ? (int) ceil(now()->diffInDays($enrollment->expires_at, false)) : null;
@endphp

@section('player')
    <header class="player-head">
        <p class="eyebrow">{{ __('account.course_home') }}</p>
        <h1>{{ $course->title }}</h1>
        @if ($course->summary)
            <p class="lead">{{ $course->summary }}</p>
        @endif
    </header>

    <div class="course-dash">
        <div class="card dash-progress">
            <div class="ring ring-animated" style="--p: {{ $progress->percent }}" role="img" aria-label="{{ __('account.progress', ['percent' => $progress->percent]) }}">
                <span><b data-count="{{ $progress->percent }}">{{ $progress->percent }}</b>%</span>
            </div>
            <div class="dash-body">
                <strong>{{ __('account.your_progress') }}</strong>
                <div class="dash-chips">
                    <span class="dash-chip"><b>{{ count($progress->completedLessonIds) }}</b>/{{ $progress->lessonsTotal }} {{ __('account.stat_lessons') }}</span>
                    @if ($progress->requiredTotal)
                        <span class="dash-chip"><b>{{ $testsDone }}</b>/{{ $progress->requiredTotal }} {{ __('account.stat_tests') }}</span>
                    @endif
                </div>
                @if ($next && ! $progress->complete)
                    <a class="next-up" href="{{ \App\Support\CourseProgress::urlFor($course, $next) }}">
                        <span class="next-icon" aria-hidden="true">
                            @if ($next instanceof \App\Models\Quiz)
                                <svg viewBox="0 0 24 24"><path d="M9 4h6l1 2h3v14H5V6h3z"/><path d="m9 13 2 2 4-4"/></svg>
                            @else
                                <svg viewBox="0 0 24 24"><path d="m8 5 11 7-11 7z"/></svg>
                            @endif
                        </span>
                        <span class="next-text">
                            <small>{{ $progress->done ? __('account.continue_learning') : __('account.start') }} · {{ __('account.next_up') }}</small>
                            <strong>{{ $next->title }}</strong>
                        </span>
                        <span class="next-arrow" aria-hidden="true">→</span>
                    </a>
                @endif
            </div>
        </div>

        <div @class(['card', 'dash-cert', 'is-ready' => $enrollment?->certificate_code])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-2.5 5 2.5-1.5-7"/></svg>
            <div>
                <strong>{{ __('account.certificate') }}</strong>
                @if ($enrollment?->certificate_code)
                    <p class="muted">{{ __('account.certificate_ready') }}</p>
                    <a class="btn btn-primary btn-sm" href="{{ route('learn.certificate', $course->slug) }}">{{ __('account.view_certificate') }}</a>
                @elseif ($course->certificate_enabled)
                    <p class="muted">{{ __('account.certificate_locked') }}</p>
                @endif
                @if ($enrollment)
                    <p @class(['muted', 'small', 'is-soon' => $daysLeft !== null && $daysLeft <= 7])>
                        {{ $enrollment->expires_at ? __('account.expires', ['date' => $enrollment->expires_at->format('d/m/Y')]) : __('account.access_lifetime') }}
                        @if ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 30) · {{ __('account.days_left', ['count' => $daysLeft]) }} @endif
                    </p>
                @endif
            </div>
        </div>
    </div>

    <h2 class="label-heading">{{ __('account.course_content') }}</h2>
    @include('partials.curriculum', ['outline' => $outline, 'course' => $course, 'hasAccess' => true, 'progress' => $progress])
@endsection
