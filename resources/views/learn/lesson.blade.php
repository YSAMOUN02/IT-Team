@extends('learn._layout', ['current' => $lesson])

@section('title', $lesson->title.' · '.$course->title)
@section('admin_edit', route('admin.lessons.edit', $lesson))

@php
    $done = $progress?->lessonDone($lesson->id) ?? false;
    $itemUrl = fn ($item) => $item instanceof \App\Models\Quiz ? route('learn.quiz', [$course->slug, $item->slug]) : route('learn.lesson', [$course->slug, $item->slug]);
@endphp

@section('player')
    <div class="read-progress" data-read-progress aria-hidden="true"><span></span></div>
    <header class="player-head">
        <p class="eyebrow">
            {{ $lesson->module?->title ?? $course->title }}
            @if ($lesson->duration)<span aria-hidden="true">·</span> {{ $lesson->duration }}@endif
        </p>
        <h1>{{ $lesson->title }}</h1>
    </header>

    @unless ($hasAccess)
        <div class="alert alert-info">{{ __('account.preview_note') }} <a href="{{ $course->url() }}#buy">{{ __('account.buy_now') }} →</a></div>
    @endunless

    @php
        // The student's email drifts over paid videos, so a screen recording shows whose account it came from.
        $watermark = $hasAccess && auth()->check() && ! auth()->user()->isAdmin() ? auth()->user()->email : null;
    @endphp
    @if ($lesson->video_path)
        <div class="video-frame">
            <video controls preload="metadata" controlsList="nodownload noplaybackrate" disablepictureinpicture oncontextmenu="return false" src="{{ route('learn.file', [$course->slug, $lesson->slug, 'video']) }}"></video>
            @if ($watermark)<span class="video-watermark" aria-hidden="true">{{ $watermark }}</span>@endif
        </div>
    @elseif ($embed = $lesson->embedUrl())
        <div class="video-frame">
            <iframe src="{{ $embed }}" title="{{ $lesson->title }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
            @if ($watermark)<span class="video-watermark" aria-hidden="true">{{ $watermark }}</span>@endif
        </div>
    @endif

    @if ($lesson->body)
        <div class="prose entry lesson-body">{!! $lesson->body !!}</div>
    @endif

    @if ($lesson->attachment_path || ($lesson->solution_path && $hasAccess))
        <div class="card lesson-files">
            <h2>{{ __('account.files') }}</h2>
            @if ($lesson->attachment_path)
                <a class="file-row" href="{{ route('learn.file', [$course->slug, $lesson->slug, 'practice']) }}">
                    <span class="file-ico xl">XLS</span>
                    <span><strong>{{ __('account.practice_file') }}</strong><small>{{ $lesson->attachment_name }}</small></span>
                    <span class="btn btn-outline btn-sm">{{ __('account.download') }}</span>
                </a>
            @endif
            @if ($lesson->solution_path && $hasAccess)
                <a class="file-row" href="{{ route('learn.file', [$course->slug, $lesson->slug, 'solution']) }}">
                    <span class="file-ico ok">✓</span>
                    <span><strong>{{ __('account.solution_file') }}</strong><small>{{ $lesson->solution_name }}</small></span>
                    <span class="btn btn-outline btn-sm">{{ __('account.download') }}</span>
                </a>
            @endif
        </div>
    @endif

    <nav class="lesson-nav">
        <div>
            @if ($previous)
                <a class="btn btn-outline" href="{{ $itemUrl($previous) }}">← {{ __('account.previous') }}</a>
            @endif
        </div>
        @if ($hasAccess && $progress)
            <form method="post" action="{{ route('learn.complete', [$course->slug, $lesson->slug]) }}" class="lesson-complete">
                @csrf
                @if ($done)
                    <span class="done-badge">✓ {{ __('account.lesson_done') }}</span>
                    <button class="link-btn" name="undo" value="1">{{ __('account.undo') }}</button>
                    @if ($next)
                        <a class="btn btn-primary" href="{{ $itemUrl($next) }}">{{ __('account.next') }} →</a>
                    @endif
                @elseif ($next)
                    <input type="hidden" name="next" value="{{ $itemUrl($next) }}">
                    <button class="btn btn-primary">{{ __('account.complete_and_next') }} →</button>
                @else
                    <button class="btn btn-primary">✓ {{ __('account.mark_complete') }}</button>
                @endif
            </form>
        @elseif ($next && ($next->is_preview || $hasAccess))
            <a class="btn btn-primary" href="{{ $itemUrl($next) }}">{{ __('account.next') }} →</a>
        @endif
    </nav>
@endsection
