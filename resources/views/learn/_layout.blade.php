{{-- Course player shell: outline sidebar + content. Children fill @section('player'). --}}
@extends('layouts.site')

@section('no_ads', true)
@section('body_class', 'page-learn')

@section('content')
    <div class="player">
        <aside class="player-side" id="player-side">
            <a class="player-course" href="{{ ($hasAccess ?? true) && auth()->check() && auth()->user()->canAccessCourse($course) ? route('learn.course', $course->slug) : $course->url() }}">
                <small>{{ __('account.course_home') }}</small>
                <strong>{{ $course->title }}</strong>
            </a>
            @if (! empty($progress))
                {{-- Just finished a lesson: the bar grows from where it was. --}}
                <div class="player-progress">
                    <div class="progress progress-animated" @if (session()->has('progress_was')) style="--from: {{ (int) session('progress_was') }}%" @endif><span style="width: {{ $progress->percent }}%"></span></div>
                    <small>{{ __('account.progress', ['percent' => $progress->percent]) }} · {{ $progress->done }}/{{ $progress->total }}</small>
                </div>
            @endif
            @include('partials.curriculum', [
                'outline' => $outline,
                'course' => $course,
                'hasAccess' => auth()->check() && auth()->user()->canAccessCourse($course),
                'progress' => $progress ?? null,
                'current' => $current ?? null,
            ])
        </aside>

        <div class="player-main">
            <button type="button" class="btn btn-outline btn-sm player-toggle" onclick="document.getElementById('player-side').classList.toggle('open')">☰ {{ __('account.course_content') }}</button>
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            @yield('player')
            @if (session('lesson_done') && ! empty($progress))
                <div class="learn-toast" role="status">
                    <span class="learn-toast-check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 12 4 4 8-9"/></svg></span>
                    <span>
                        <strong>{{ __('account.lesson_complete_toast') }}</strong>
                        <small>{{ session('lesson_done') }} · {{ $progress->done }}/{{ $progress->total }}</small>
                    </span>
                </div>
            @endif
        </div>
    </div>
@endsection
