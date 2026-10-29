@extends('layouts.site')

@section('title', $quiz->title)
@section('no_ads', true)

@php
    $rows = $questions->map(function ($question) use ($attempt) {
        $chosen = array_map('intval', $attempt->answers[$question->id] ?? []);

        return ['question' => $question, 'chosen' => $chosen, 'right' => $question->isAnsweredCorrectly($chosen)];
    });
    $rightCount = $rows->where('right', true)->count();
    $unanswered = $rows->filter(fn ($row) => ! $row['chosen'])->count();
    $wrongCount = $rows->count() - $rightCount - $unanswered;
    // Attempts saved before the 2026_11_01_000002 fix lost their real start time (it equals the submit time): no time shown.
    $seconds = $attempt->started_at && $attempt->submitted_at?->gt($attempt->started_at) ? (int) $attempt->started_at->diffInSeconds($attempt->submitted_at) : null;
    $practice = $quiz->isPractice();
    $celebrate = $attempt->passed && ! $practice;
    $courseDone = $progress->complete && $certificateCode && in_array($quiz->type, ['final', 'quiz'], true);
@endphp

@section('content')
    <section class="section result-page">
        <div class="container narrow">
            <div @class(['card', 'result-card', 'is-pass' => $attempt->passed, 'is-fail' => ! $attempt->passed, 'is-practice' => $practice]) @if ($celebrate) data-confetti @endif>
                <p class="eyebrow"><em class="tag tag-{{ $quiz->type }}">{{ $quiz->typeLabel() }}</em> {{ $course->title }}</p>
                <h1>{{ $quiz->title }}</h1>

                <div class="ring ring-lg ring-animated" style="--p: {{ $attempt->percent }}" role="img" aria-label="{{ $attempt->percent }}%">
                    <span><b data-count="{{ $attempt->percent }}">{{ $attempt->percent }}</b>%</span>
                </div>
                <p class="result-verdict">
                    @if ($practice)
                        {{ __('account.result_practice') }}
                    @else
                        {{ $attempt->passed ? __('account.result_great') : __('account.not_passed') }}
                    @endif
                </p>
                <p class="muted">{{ __('account.score') }}: {{ $attempt->score }}/{{ $attempt->max_score }} · {{ __('account.pass_mark', ['percent' => $quiz->pass_percent]) }}</p>
                @if (! $attempt->passed && ! $practice)
                    <p class="result-encourage">{{ __('account.result_try_again') }}</p>
                @endif

                <ul class="result-stats">
                    <li class="is-right" style="--share: {{ $rows->count() ? $rightCount / $rows->count() : 0 }}"><strong>{{ $rightCount }}</strong><span>{{ __('account.stat_correct') }}</span><i></i></li>
                    <li class="is-wrong" style="--share: {{ $rows->count() ? $wrongCount / $rows->count() : 0 }}"><strong>{{ $wrongCount }}</strong><span>{{ __('account.stat_wrong') }}</span><i></i></li>
                    <li class="is-empty" style="--share: {{ $rows->count() ? $unanswered / $rows->count() : 0 }}"><strong>{{ $unanswered }}</strong><span>{{ __('account.unanswered') }}</span><i></i></li>
                    @if ($seconds !== null)
                        <li class="is-time"><strong>{{ intdiv($seconds, 60) }}:{{ str_pad($seconds % 60, 2, '0', STR_PAD_LEFT) }}</strong><span>{{ __('account.stat_time') }}</span></li>
                    @endif
                </ul>

                @if ($courseDone)
                    <div class="result-course-done">
                        <strong>{{ __('account.course_done_title') }}</strong>
                        <a class="btn btn-primary" href="{{ route('learn.certificate', $course->slug) }}">{{ __('account.view_certificate') }}</a>
                    </div>
                @endif

                <div class="btn-row btn-row-center">
                    @if ($next)
                        <a class="btn btn-primary" href="{{ \App\Support\CourseProgress::urlFor($course, $next) }}">{{ __('account.continue_to', ['title' => \Illuminate\Support\Str::limit($next->title, 40)]) }} →</a>
                    @else
                        <a class="btn btn-primary" href="{{ route('learn.course', $course->slug) }}">{{ __('account.back_to_course') }}</a>
                    @endif
                    @if ($attemptsLeft !== 0)
                        <form method="post" action="{{ route('learn.quiz.start', [$course->slug, $quiz->slug]) }}">
                            @csrf
                            <button class="btn btn-outline">{{ __('account.retake') }}</button>
                        </form>
                    @elseif ($canRequestRetake)
                        <a class="btn btn-outline" href="{{ route('learn.quiz', [$course->slug, $quiz->slug]) }}#retake">{{ __('account.retake_ask') }}</a>
                    @endif
                    @if ($next)
                        <a class="btn btn-ghost" href="{{ route('learn.course', $course->slug) }}">{{ __('account.back_to_course') }}</a>
                    @endif
                </div>
            </div>

            @if ($quiz->show_answers)
                <div class="review-head">
                    <h2 class="label-heading">{{ __('account.review_answers') }}</h2>
                    <div class="review-filter" role="group" data-review-filter>
                        <button type="button" aria-pressed="true" data-filter="all">{{ __('account.filter_all') }} <b>{{ $rows->count() }}</b></button>
                        <button type="button" aria-pressed="false" data-filter="wrong">{{ __('account.filter_wrong') }} <b>{{ $rows->count() - $rightCount }}</b></button>
                        <button type="button" aria-pressed="false" data-filter="right">{{ __('account.filter_correct') }} <b>{{ $rightCount }}</b></button>
                    </div>
                </div>
                @foreach ($rows as $i => ['question' => $question, 'chosen' => $chosen, 'right' => $right])
                    <div @class(['card', 'question', 'review', 'is-right' => $right, 'is-wrong' => ! $right]) data-review="{{ $right ? 'right' : 'wrong' }}">
                        <p class="q-head">
                            <span class="q-num">{{ $i + 1 }}</span>
                            <span class="q-prompt">{!! nl2br(e($question->prompt)) !!}</span>
                            <span class="pill {{ $right ? 'pill-paid' : 'pill-failed' }}">{{ $right ? __('account.correct') : ($chosen ? __('account.incorrect') : __('account.unanswered')) }}</span>
                        </p>
                        @if ($question->image)
                            <img class="q-image" src="{{ media_url($question->image) }}" alt="">
                        @endif
                        <div class="q-options">
                            @foreach ($question->options as $option)
                                <div @class(['q-option', 'is-right' => $option->is_correct, 'is-wrong' => ! $option->is_correct && in_array($option->id, $chosen)])>
                                    <span class="q-mark">{{ chr(65 + $loop->index) }}</span>
                                    <span>{{ $option->text }}</span>
                                    @if (in_array($option->id, $chosen))<small>{{ __('account.your_answer') }}</small>@elseif ($option->is_correct)<small>{{ __('account.correct_answer') }}</small>@endif
                                </div>
                            @endforeach
                        </div>
                        @if ($question->explanation)
                            <p class="q-explain"><strong>{{ __('account.explanation') }}:</strong> {{ $question->explanation }}</p>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            // Review filter: all / wrong / correct
            const filter = document.querySelector('[data-review-filter]');
            filter?.addEventListener('click', (e) => {
                const button = e.target.closest('[data-filter]');
                if (!button) return;
                filter.querySelectorAll('[data-filter]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
                document.querySelectorAll('[data-review]').forEach((card) => {
                    card.hidden = button.dataset.filter !== 'all' && card.dataset.review !== button.dataset.filter;
                });
            });

            // A short burst of confetti for a pass (skipped when the visitor prefers less motion).
            const card = document.querySelector('[data-confetti]');
            if (!card || !document.documentElement.classList.contains('motion')) return;
            const canvas = document.createElement('canvas');
            canvas.className = 'confetti';
            canvas.setAttribute('aria-hidden', 'true');
            document.body.appendChild(canvas);
            const ctx = canvas.getContext('2d');
            const dpr = window.devicePixelRatio || 1;
            const resize = () => { canvas.width = innerWidth * dpr; canvas.height = innerHeight * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0); };
            resize();
            const box = card.querySelector('.ring').getBoundingClientRect();
            const colors = ['#5f59f7', '#343090', '#0f9d58', '#f4b400', '#db4437', '#9b8cff'];
            // Pieces start on the edge of the score ring and fly outwards, so the score stays readable.
            const bits = Array.from({ length: 140 }, () => {
                const angle = Math.random() * Math.PI * 2;
                const speed = 5 + Math.random() * 9;
                return {
                    x: box.left + box.width / 2 + Math.cos(angle) * box.width / 2,
                    y: box.top + box.height / 2 + Math.sin(angle) * box.height / 2,
                    vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed - 6,
                    size: 5 + Math.random() * 6, spin: Math.random() * 6, turn: (Math.random() - 0.5) * 0.3,
                    color: colors[Math.floor(Math.random() * colors.length)],
                };
            });
            const start = performance.now();
            const frame = (now) => {
                const t = now - start;
                ctx.clearRect(0, 0, innerWidth, innerHeight);
                bits.forEach((b) => {
                    b.vy += 0.32; b.vx *= 0.99; b.x += b.vx; b.y += b.vy; b.spin += b.turn;
                    ctx.save();
                    ctx.globalAlpha = Math.max(0, 1 - t / 2600);
                    ctx.translate(b.x, b.y);
                    ctx.rotate(b.spin);
                    ctx.fillStyle = b.color;
                    ctx.fillRect(-b.size / 2, -b.size / 4, b.size, b.size / 2);
                    ctx.restore();
                });
                if (t < 2600) requestAnimationFrame(frame); else canvas.remove();
            };
            setTimeout(() => requestAnimationFrame(frame), 650); // once the score ring has filled
        })();
    </script>
@endpush
