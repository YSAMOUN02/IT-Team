@extends('learn._layout', ['current' => $quiz])

@section('title', $quiz->title.' · '.$course->title)
@section('admin_edit', route('admin.quizzes.edit', $quiz))

@php
    $best = $attempts->sortByDesc('percent')->first();
@endphp

@section('player')
    <header class="player-head">
        <p class="eyebrow"><em class="tag tag-{{ $quiz->type }}">{{ $quiz->typeLabel() }}</em> {{ $quiz->module?->title }}</p>
        <h1>{{ $quiz->title }}</h1>
        @if ($quiz->description)
            <p class="lead">{{ $quiz->description }}</p>
        @endif
    </header>

    <div class="card quiz-intro">
        <ul class="quiz-facts">
            <li><strong>{{ $quiz->questions_count }}</strong><span>{{ trans_choice('account.questions_count', $quiz->questions_count, ['count' => $quiz->questions_count]) }}</span></li>
            <li><strong>{{ $quiz->time_limit ? $quiz->time_limit."′" : '∞' }}</strong><span>{{ $quiz->time_limit ? __('account.time_limit', ['minutes' => $quiz->time_limit]) : __('account.no_time_limit') }}</span></li>
            <li><strong>{{ $quiz->pass_percent }}%</strong><span>{{ __('account.pass_mark', ['percent' => $quiz->pass_percent]) }}</span></li>
            <li><strong>{{ $attemptsLeft ?? '∞' }}</strong><span>{{ $attemptsLeft === null ? __('account.unlimited_attempts') : trans_choice('account.attempts_left', $attemptsLeft, ['count' => $attemptsLeft]) }}</span></li>
        </ul>
        <p class="quiz-note">{{ __('account.note_'.$quiz->type) }}</p>

        @if ($best)
            <p class="best-score">
                {{ __('account.best_score') }}: <strong>{{ $best->percent }}%</strong>
                <span class="pill {{ $best->passed ? 'pill-paid' : 'pill-failed' }}">{{ $best->passed ? __('account.passed') : __('account.not_passed') }}</span>
            </p>
        @endif

        @if ($quiz->questions_count === 0)
            <p class="muted">—</p>
        @elseif ($open && ! $open->isOverdue())
            <a class="btn btn-primary btn-lg" href="{{ route('learn.attempt', [$course->slug, $quiz->slug, $open]) }}">{{ __('account.resume') }} →</a>
        @elseif ($attemptsLeft === 0)
            <p class="alert alert-info">{{ __('account.no_attempts_left') }}</p>
        @else
            <form method="post" action="{{ route('learn.quiz.start', [$course->slug, $quiz->slug]) }}">
                @csrf
                <button class="btn btn-primary btn-lg">{{ $attempts->isEmpty() ? __('account.start') : __('account.retake') }} →</button>
            </form>
        @endif
    </div>

    @if ($attempts->isNotEmpty())
        <h2 class="label-heading">{{ __('account.your_attempts') }}</h2>
        <div class="card table-card">
            <table class="simple-table">
                <tbody>
                @foreach ($attempts as $attempt)
                    <tr>
                        <td>{{ $attempt->submitted_at->format('d/m/Y H:i') }}</td>
                        <td><strong>{{ $attempt->percent }}%</strong> <small>({{ $attempt->score }}/{{ $attempt->max_score }})</small></td>
                        <td><span class="pill {{ $attempt->passed ? 'pill-paid' : 'pill-failed' }}">{{ $attempt->passed ? __('account.passed') : __('account.not_passed') }}</span></td>
                        <td><a href="{{ route('learn.result', [$course->slug, $quiz->slug, $attempt]) }}">{{ __('account.review_answers') }} →</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
