@extends('admin.layout')

@section('title', 'Exam results')
@section('subtitle', 'Every practice test, module quiz, mock and final exam your students took. Open one to see each answer.')
@section('crumbs')<span aria-hidden="true">/</span><span>Sales</span><span aria-hidden="true">/</span><span>Exam results</span>@endsection

@section('content')
<div class="reveal">
    <div class="kpis kpis-mini">
        <div class="card kpi"><div class="kpi-label">Tests submitted</div><div class="kpi-value">{{ $stats['attempts'] }}</div><div class="kpi-note">{{ $stats['students'] }} students</div></div>
        <div class="card kpi"><div class="kpi-label">Average score</div><div class="kpi-value">{{ $stats['attempts'] ? $stats['average'].'%' : '—' }}</div></div>
        <div class="card kpi"><div class="kpi-label">Pass rate</div><div class="kpi-value">{{ $stats['pass_rate'] !== null ? $stats['pass_rate'].'%' : '—' }}</div></div>
        <div class="card kpi"><div class="kpi-label">Not submitted</div><div class="kpi-value">{{ $stats['unfinished'] }}</div><div class="kpi-note">still taking it, or time ran out</div></div>
    </div>

    <div class="card">
        <form class="toolbar" method="get">
            @if ($quiz)<input type="hidden" name="quiz" value="{{ $quiz->id }}">@endif
            <div class="search">
                @include('admin.partials.icon', ['name' => 'search'])
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Student name or email…" aria-label="Search students">
            </div>
            @unless ($quiz)
                <select name="course" aria-label="Course" onchange="this.form.submit()">
                    <option value="">All courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) request('course') === (string) $course->id)>{{ $course->title }}</option>
                    @endforeach
                </select>
                <select name="type" aria-label="Test" onchange="this.form.submit()">
                    <option value="">All tests</option>
                    @foreach (\App\Models\Quiz::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @endunless
            <select name="result" aria-label="Result" onchange="this.form.submit()">
                <option value="">Any result</option>
                @foreach (\App\Http\Controllers\Admin\ResultController::RESULTS as $value => $label)
                    <option value="{{ $value }}" @selected(request('result') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($quiz)
                <a class="btn btn-ghost btn-sm" href="{{ route('admin.results.index') }}" title="Show every test">{{ $quiz->title }} · {{ $quiz->course?->title }} &times;</a>
            @endif
            <span class="toolbar-count">{{ $attempts->total() }} attempts</span>
        </form>

        @if ($attempts->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'check'])</div>
                <h3>No results {{ array_filter(request()->only('q', 'course', 'type', 'result', 'quiz')) ? 'match' : 'yet' }}</h3>
                <p>Results appear here as soon as a student starts a test.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data results">
                    <thead><tr><th>Student</th><th>Test</th><th>Score</th><th>Result</th><th>Time</th><th>Date</th></tr></thead>
                    <tbody>
                    @foreach ($attempts as $attempt)
                        @php
                            [$try, $of] = $tries[$attempt->id] ?? [1, 1];
                            // Attempts saved before the 2026_11_01_000002 fix lost their real start time (it equals the submit time).
                            $seconds = $attempt->isSubmitted() && $attempt->submitted_at->gt($attempt->started_at) ? (int) $attempt->started_at->diffInSeconds($attempt->submitted_at) : null;
                        @endphp
                        <tr>
                            <td>
                                <div class="row-title">
                                    <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($attempt->user->name, 0, 1)) }}</span>
                                    <div><a href="{{ route('admin.students.show', $attempt->user) }}">{{ $attempt->user->name }}</a><span class="row-sub">{{ $attempt->user->email }}</span></div>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('admin.results.show', $attempt) }}">{{ $attempt->quiz->title }}</a>
                                <span class="row-sub" title="{{ $attempt->quiz->course?->title }}"><b class="qtype qtype-{{ $attempt->quiz->type }}">{{ \App\Models\Quiz::TYPES[$attempt->quiz->type] }}</b> {{ $attempt->quiz->course?->title }}</span>
                            </td>
                            <td style="min-width:120px">
                                @if ($attempt->isSubmitted())
                                    <b>{{ $attempt->percent }}%</b>
                                    <div class="mini-progress"><span style="width: {{ $attempt->percent }}%"></span></div>
                                    <span class="row-sub">{{ $attempt->score }}/{{ $attempt->max_score }} · pass {{ $attempt->quiz->pass_percent }}%</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>@include('admin.results._status')@if ($of > 1)<span class="row-sub">try {{ $try }} of {{ $of }}</span>@endif</td>
                            <td class="num">{{ $seconds !== null ? intdiv($seconds, 60).':'.str_pad($seconds % 60, 2, '0', STR_PAD_LEFT) : '—' }}</td>
                            <td class="num">{{ ($attempt->submitted_at ?? $attempt->started_at)->format('d M Y') }}<span class="row-sub">{{ ($attempt->submitted_at ?? $attempt->started_at)->format('H:i') }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $attempts->links('admin.pager') }}
        @endif
    </div>
</div>
@endsection
