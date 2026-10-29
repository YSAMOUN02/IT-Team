@extends('admin.layout')

@php
    $student = $attempt->user;
    $rightCount = $rows->where('right', true)->count();
    $unanswered = $rows->filter(fn ($row) => ! $row['chosen'])->count();
    $wrongCount = $rows->count() - $rightCount - $unanswered;
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
                        <li @class(['review-q', 'is-right' => $right, 'is-wrong' => ! $right]) data-review="{{ $right ? 'right' : 'wrong' }}">
                            <div class="review-q-head">
                                <span class="q-num">{{ $loop->iteration }}</span>
                                <span class="q-prompt">{!! nl2br(e($question->prompt)) !!}</span>
                                <span class="status {{ $right ? 'status-on' : ($chosen ? 'status-blocked' : 'status-off') }}">{{ $right ? 'Correct' : ($chosen ? 'Wrong' : 'Not answered') }}</span>
                            </div>
                            @if ($question->image)
                                <img class="review-image" src="{{ media_url($question->image) }}" alt="">
                            @endif
                            <ul class="review-opts">
                                @foreach ($question->options as $option)
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
