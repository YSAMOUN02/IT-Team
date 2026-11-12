@extends('admin.layout')

@php
    $student = $attempt->user;
    $rightCount = $rows->where('right', true)->count();
    $unanswered = $rows->filter(fn ($row) => ! $row['question']->hasAnswer($row['chosen']))->count();
    $toMark = $rows->filter(fn ($row) => $row['right'] === null)->count();
    $wrongCount = $rows->count() - $rightCount - $unanswered - $toMark;
    // Attempts saved before the 2026_11_01_000002 fix lost their real start time (it equals the submit time).
    $seconds = $attempt->isSubmitted() && $attempt->submitted_at->gt($attempt->started_at) ? (int) $attempt->started_at->diffInSeconds($attempt->submitted_at) : null;
@endphp

@section('title', $quiz->title)
@section('subtitle', $student->name.' · '.\App\Models\Quiz::TYPES[$quiz->type].' · '.$quiz->course?->title)
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.results.index') }}">Exam results</a>
    <span aria-hidden="true">/</span><span>Attempt</span>
@endsection
@section('actions')
    <a class="btn btn-ghost" href="{{ route('admin.students.show', $student) }}">@include('admin.partials.icon', ['name' => 'user']) {{ $student->name }}</a>
    <a class="btn btn-ghost" href="{{ route('admin.results.index', ['quiz' => $quiz->id]) }}">Everyone's results for this test</a>
@endsection

@section('content')
<div class="reveal">
    @if (! $attempt->isSubmitted())
        <div class="alert alert-error" role="status">
            @include('admin.partials.icon', ['name' => 'clock'])
            <div><strong>Not submitted.</strong> Started {{ $attempt->started_at->format('d M Y, H:i') }}
                @if ($attempt->deadline()) · time limit {{ $quiz->time_limit }} min (until {{ $attempt->deadline()->format('H:i') }}) @endif
                · @include('admin.results._status')</div>
        </div>
    @else
        <div class="kpis kpis-mini kpis-5">
            <div class="card kpi"><div class="kpi-label">Score</div><div class="kpi-value">{{ $attempt->percent }}%</div><div class="kpi-note">{{ $attempt->score }}/{{ $attempt->max_score }} points · pass {{ $quiz->pass_percent }}%</div></div>
            <div class="card kpi"><div class="kpi-label">Result</div><div class="kpi-value" style="font-size:18px;margin-top:8px">@include('admin.results._status')</div></div>
            <div class="card kpi"><div class="kpi-label">Correct</div><div class="kpi-value">{{ $rightCount }}</div><div class="kpi-note">of {{ $rows->count() }} questions</div></div>
            <div class="card kpi"><div class="kpi-label">Wrong / not answered</div><div class="kpi-value">{{ $wrongCount }} / {{ $unanswered }}</div></div>
            <div class="card kpi"><div class="kpi-label">Time taken</div><div class="kpi-value">{{ $seconds !== null ? intdiv($seconds, 60).':'.str_pad($seconds % 60, 2, '0', STR_PAD_LEFT) : '—' }}</div><div class="kpi-note">{{ $quiz->time_limit ? 'limit '.$quiz->time_limit.' min · ' : '' }}{{ $attempt->submitted_at->format('d M Y, H:i') }}</div></div>
        </div>
    @endif

    <div class="form-grid">
        <section class="card">
            <div class="card-head">
                <div><h2>Answers</h2><p>What {{ $student->name }} chose, next to the right answer.</p></div>
                @if ($rows->isNotEmpty())
                    <div class="segmented" role="radiogroup" aria-label="Show" data-review-filter>
                        <label class="seg"><input type="radio" name="review" value="all" checked><span>All {{ $rows->count() }}</span></label>
                        <label class="seg"><input type="radio" name="review" value="wrong"><span>Wrong {{ $rows->count() - $rightCount }}</span></label>
                        <label class="seg"><input type="radio" name="review" value="right"><span>Correct {{ $rightCount }}</span></label>
                    </div>
                @endif
            </div>
            @if ($rows->isEmpty())
                <div class="empty-state"><p>The answers show here once the test is submitted.</p></div>
            @else
                <ol class="review-list">
                    @foreach ($rows as ['question' => $question, 'chosen' => $chosen, 'right' => $right])
                        <li @class(['review-q', 'is-right' => $right, 'is-wrong' => $right === false, 'is-to-mark' => $right === null]) id="q{{ $question->id }}" data-review="{{ $right ? 'right' : 'wrong' }}">
                            <div class="review-q-head">
                                <span class="q-num">{{ $loop->iteration }}</span>
                                <span class="q-prompt">{!! nl2br(e($question->prompt)) !!}</span>
                                @php($given = $question->hasAnswer($chosen))
                                @php($pct = $question->isMarkedByTeacher() && $given ? $attempt->markPercent($question) : null)
                                @if ($pct !== null)
                                    <span class="status {{ $pct === 100 ? 'status-on' : ($pct === 0 ? 'status-blocked' : 'status-sched') }}">{{ $pct }}% · {{ \App\Models\QuizQuestion::pointsText($attempt->pointsFor($question)) }} / {{ $question->points }} pt</span>
                                @else
                                    <span class="status {{ $right ? 'status-on' : ($right === null ? 'status-sched' : ($given ? 'status-blocked' : 'status-off')) }}">{{ $right ? 'Correct' : ($right === null ? 'To mark' : ($given ? 'Wrong' : 'Not answered')) }}</span>
                                @endif
                            </div>
                            @if ($question->image)
                                <img class="review-image" src="{{ media_url($question->image) }}" alt="">
                            @endif
                            @if ($question->isMarkedByTeacher())
                                {{-- Short or long answer: the teacher reads it and gives 0-100% of the points. --}}
                                <div class="mark-box">
                                    <div class="mark-answer"><small>Student's answer</small>{{ $given ? $chosen : '(no answer)' }}</div>
                                    @if ($question->explanation)
                                        <div class="mark-model"><small>Model answer</small>{{ $question->explanation }}</div>
                                    @endif
                                    @if ($given)
                                        <form method="post" action="{{ route('admin.results.mark', $attempt) }}" class="mark-actions">
                                            @csrf
                                            <input type="hidden" name="question_id" value="{{ $question->id }}">
                                            <span class="mark-label">Mark <small>{{ $question->points }} {{ $question->points === 1 ? 'point' : 'points' }}</small></span>
                                            <div class="mark-scale" role="group" aria-label="Mark">
                                                @foreach (\App\Models\QuizQuestion::MARK_STEPS as $step)
                                                    <button @class(['mark-step', 'is-on' => $pct === $step]) name="percent" value="{{ $step }}" @if ($pct === $step) aria-pressed="true" @endif>
                                                        <b>{{ $step }}%</b><small>{{ \App\Models\QuizQuestion::pointsText($question->points * $step / 100) }} pt</small>
                                                    </button>
                                                @endforeach
                                            </div>
                                            @if ($pct !== null)<span class="help">Marked {{ $pct }}%. Click another mark to change it.</span>@endif
                                        </form>
                                    @endif
                                </div>
                            @endif
                            @unless ($question->isChoice() || $question->isMarkedByTeacher())
                                {{-- Matching, ordering, drag & drop: part by part. --}}
                                <table class="review-parts">
                                    <thead><tr><th>{{ ['matching' => 'Item', 'ordering' => 'Place', 'dragdrop' => 'Blank'][$question->type] ?? '' }}</th><th>Student's answer</th><th>Right answer</th></tr></thead>
                                    <tbody>
                                    @foreach ($question->reviewRows($chosen) as $part)
                                        <tr @class(['is-correct' => $part['right'], 'is-picked-wrong' => ! $part['right']])>
                                            <td>{{ $part['label'] }}</td>
                                            <td>{{ $part['given'] ?? '—' }}</td>
                                            <td>{{ $part['correct'] }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            @endunless
                            <ul class="review-opts" @unless ($question->isChoice()) hidden @endunless>
                                @foreach ($question->isChoice() ? $question->options : [] as $option)
                                    @php($picked = in_array($option->id, $chosen))
                                    <li @class(['is-correct' => $option->is_correct, 'is-picked-wrong' => $picked && ! $option->is_correct])>
                                        <span class="q-mark">{{ chr(65 + $loop->index) }}</span>
                                        <span>{{ $option->text }}</span>
                                        @if ($picked && $option->is_correct)<small>Student's answer · correct</small>
                                        @elseif ($picked)<small>Student's answer</small>
                                        @elseif ($option->is_correct)<small>Right answer</small>@endif
                                    </li>
                                @endforeach
                            </ul>
                            @if ($question->explanation)
                                <p class="review-explain"><b>Explanation:</b> {{ $question->explanation }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <div class="form-side">
            <section class="card">
                @php($given = (int) $retakes->where('status', 'approved')->sum('extra_attempts'))
                @php($pending = $retakes->firstWhere('status', 'pending'))
                <div class="card-head"><div><h2>{{ $student->name }}'s tries</h2><p>{{ $quiz->max_attempts ? $others->whereNotNull('submitted_at')->count().' of '.($quiz->max_attempts + $given).' allowed'.($given ? ' ('.$quiz->max_attempts.' + '.$given.' given by a teacher)' : '') : 'No limit on tries' }}</p></div></div>
                <ul class="feed">
                    @foreach ($others as $other)
                        <li @class(['is-current' => $other->is($attempt)])>
                            <div class="feed-main">
                                <a class="feed-title" href="{{ route('admin.results.show', $other) }}">Try {{ $loop->iteration }}{{ $other->is($attempt) ? ' (this one)' : '' }}</a>
                                <span class="feed-meta">{{ ($other->submitted_at ?? $other->started_at)->format('d M Y, H:i') }}</span>
                            </div>
                            @if ($other->isSubmitted())<b>{{ $other->percent }}%</b>@endif
                            @include('admin.results._status', ['attempt' => $other->setRelation('quiz', $quiz)])
                        </li>
                    @endforeach
                </ul>
                @if ($quiz->max_attempts && $attemptsLeft === 0 && ! $others->contains('passed', true) && ! $student->isAdmin())
                    <div class="card-body fields retake-side">
                        @if ($pending)
                            <p style="margin:0"><span class="status status-sched">Asked for a re-exam</span> <span class="muted">{{ $pending->created_at->diffForHumans() }}</span></p>
                            @if ($pending->message)<blockquote class="retake-message">“{{ $pending->message }}”</blockquote>@endif
                        @else
                            <p class="muted" style="margin:0">No tries left and not passed.</p>
                        @endif
                        <form method="post" action="{{ route('admin.results.extra-try', $attempt) }}" class="retake-buttons">
                            @csrf
                            <select name="extra_attempts" aria-label="Tries to give">
                                @foreach ([1, 2, 3] as $n)
                                    <option value="{{ $n }}">{{ $n }} more {{ $n === 1 ? 'try' : 'tries' }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary btn-sm">{{ $pending ? 'Approve' : 'Give more tries' }}</button>
                        </form>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Answers: show all / only wrong / only correct
    document.querySelector('[data-review-filter]')?.addEventListener('change', (e) => {
        document.querySelectorAll('[data-review]').forEach((q) => {
            q.hidden = e.target.value !== 'all' && q.dataset.review !== e.target.value;
        });
    });
</script>
@endpush
