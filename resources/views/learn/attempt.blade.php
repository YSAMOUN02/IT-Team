@extends('layouts.site')

@section('title', $quiz->title)
@section('no_ads', true)
@section('body_class', 'page-exam')

@section('content')
    <form method="post" action="{{ route('learn.submit', [$course->slug, $quiz->slug, $attempt]) }}" class="exam" id="exam"
          data-attempt="{{ $attempt->id }}" data-seconds="{{ $secondsLeft ?? '' }}" data-total-seconds="{{ $quiz->time_limit ? $quiz->time_limit * 60 : '' }}"
          data-practice="{{ $quiz->isPractice() ? 1 : 0 }}"
          data-check-url="{{ route('learn.check', [$course->slug, $quiz->slug, '__Q__']) }}"
          data-confirm-text="{{ __('account.submit_confirm') }}">
        @csrf

        <header class="exam-bar">
            <div class="container exam-bar-inner">
                <div class="exam-title">
                    <small><em class="tag tag-{{ $quiz->type }}">{{ $quiz->typeLabel() }}</em> {{ $course->title }}</small>
                    <strong>{{ $quiz->title }}</strong>
                </div>
                <div class="exam-stats">
                    @if ($quiz->isPractice())
                        <span class="exam-score" data-score hidden>✓ <b>0</b></span>
                    @endif
                    <span class="exam-count" data-answered aria-live="polite"><b>0</b>/{{ $questions->count() }}</span>
                    @if ($secondsLeft !== null)
                        <span class="exam-timer" title="{{ __('account.time_left') }}">
                            <svg viewBox="0 0 36 36" aria-hidden="true"><circle class="timer-track" cx="18" cy="18" r="15.9" pathLength="100"/><circle class="timer-bar" cx="18" cy="18" r="15.9" pathLength="100" data-timer-ring/></svg>
                            <span data-timer aria-live="off">--:--</span>
                        </span>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary btn-sm exam-submit-top">{{ __('account.submit') }}</button>
            </div>
            <div class="exam-progress" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $questions->count() }}" aria-valuenow="0" aria-label="{{ __('account.answered', ['done' => 0, 'total' => $questions->count()]) }}">
                <span data-progress-bar></span>
            </div>
            <nav class="container exam-nav" aria-label="{{ trans_choice('account.questions_count', $questions->count(), ['count' => $questions->count()]) }}">
                @foreach ($questions as $i => $question)
                    <a href="#q{{ $question->id }}" data-nav="{{ $question->id }}" title="{{ __('account.question_of', ['n' => $i + 1, 'total' => $questions->count()]) }}">{{ $i + 1 }}</a>
                @endforeach
            </nav>
        </header>

        <div class="container narrow exam-body">
            @if ($quiz->isPractice())
                <div class="alert alert-info">{{ __('account.note_practice') }}</div>
            @endif
            <p class="exam-keys">{{ __('account.keys_hint') }}</p>

            @foreach ($questions as $i => $question)
                @php
                    $multiple = $question->type === 'multiple';
                @endphp
                <fieldset class="card question" id="q{{ $question->id }}" data-question="{{ $question->id }}" style="--i: {{ min($i, 6) }}">
                    <legend>
                        <span class="q-num">{{ $i + 1 }}</span>
                        <span class="q-prompt">{!! nl2br(e($question->prompt)) !!}</span>
                    </legend>
                    @if ($question->image)
                        <img class="q-image" src="{{ media_url($question->image) }}" alt="">
                    @endif
                    <p class="q-hint">
                        <span>{{ __('account.question_of', ['n' => $i + 1, 'total' => $questions->count()]) }}</span>
                        · {{ $multiple ? __('account.select_many') : __('account.select_one') }} @if ($question->points > 1)· {{ $question->points }} pts @endif
                    </p>
                    <div class="q-options">
                        @foreach ($question->options as $option)
                            <label class="q-option" data-option="{{ $option->id }}">
                                <input type="{{ $multiple ? 'checkbox' : 'radio' }}" name="answers[{{ $question->id }}][]" value="{{ $option->id }}">
                                <span class="q-mark" aria-hidden="true">{{ chr(65 + $loop->index) }}</span>
                                <span>{{ $option->text }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if ($quiz->isPractice())
                        <div class="q-check">
                            <button type="button" class="btn btn-outline btn-sm" data-check>{{ __('account.check_answer') }}</button>
                            <div class="q-feedback" data-feedback hidden
                                 data-correct="{{ __('account.correct') }}" data-incorrect="{{ __('account.incorrect') }}" data-explain="{{ __('account.explanation') }}"></div>
                        </div>
                    @endif
                </fieldset>
            @endforeach

            <div class="exam-submit">
                <button type="submit" class="btn btn-primary btn-lg">{{ __('account.submit') }}</button>
            </div>
        </div>

        <dialog class="exam-dialog" data-submit-dialog>
            <h2>{{ __('account.submit_title') }}</h2>
            <div class="exam-dialog-meter"><span data-dialog-bar></span></div>
            <p data-dialog-count data-template="{{ __('account.submit_answered', ['done' => ':done', 'total' => ':total']) }}"></p>
            <div data-dialog-missing hidden>
                <p class="muted">{{ __('account.submit_unanswered') }}</p>
                <div class="exam-dialog-chips" data-dialog-chips></div>
            </div>
            <p class="muted" data-dialog-all hidden>{{ __('account.submit_all_done') }}</p>
            <div class="btn-row">
                <button type="button" class="btn btn-outline" data-dialog-cancel>{{ __('account.keep_answering') }}</button>
                <button type="button" class="btn btn-primary" data-dialog-confirm data-busy="{{ __('account.submitting') }}">{{ __('account.submit_now') }}</button>
            </div>
        </dialog>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/exam.js') }}?v={{ filemtime(public_path('js/exam.js')) }}" defer></script>
@endpush
