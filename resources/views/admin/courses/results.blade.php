@extends('admin.layout')

@php
    $tones = ['learning' => 'status-sched', 'completed' => 'status-on', 'retake' => 'status-blocked', 'not_started' => 'status-off', 'ended' => 'status-off'];
    $statuses = \App\Http\Controllers\Admin\ResultController::COURSE_STATUSES;
@endphp

@section('title', $course->title.' · Results')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.courses.index') }}">Courses</a>
    <span aria-hidden="true">/</span><span>Results</span>
@endsection
@section('hide_head', true)

@section('content')
<div class="reveal">
    @include('admin.courses._workspace', ['course' => $course, 'tab' => 'results'])

    <div class="kpis kpis-mini">
        <div class="card kpi"><div class="kpi-label">Students</div><div class="kpi-value">{{ $rows->count() }}</div><div class="kpi-note">{{ $counts['learning'] ?? 0 }} learning now</div></div>
        <div class="card kpi"><div class="kpi-label">Completed</div><div class="kpi-value">{{ $counts['completed'] ?? 0 }}</div><div class="kpi-note">{{ $rows->count() ? round(($counts['completed'] ?? 0) * 100 / $rows->count()) : 0 }}% of students · certificate</div></div>
        <div class="card kpi"><div class="kpi-label">Need to retake a test</div><div class="kpi-value">{{ $counts['retake'] ?? 0 }}</div><div class="kpi-note">took a quiz or the final and did not pass</div></div>
        @if ($final)
            <div class="card kpi"><div class="kpi-label">Passed the final exam</div><div class="kpi-value">{{ $finalPassed }}<span class="muted" style="font-size:16px"> / {{ $finalTakers }}</span></div><div class="kpi-note">of those who took it · pass {{ $final->pass_percent }}%</div></div>
        @else
            <div class="card kpi"><div class="kpi-label">Not started</div><div class="kpi-value">{{ $counts['not_started'] ?? 0 }}</div></div>
        @endif
    </div>

    <div class="card">
        <div class="toolbar">
            <nav class="segmented" aria-label="Show">
                <a href="{{ route('admin.courses.results', $course) }}" @class(['on' => ! $show])>All <span class="muted mono">{{ $rows->count() }}</span></a>
                @foreach ($statuses as $key => $label)
                    @if ($counts[$key] ?? 0)
                        <a href="{{ route('admin.courses.results', [$course, 'show' => $key]) }}" @class(['on' => $show === $key])>{{ $label }} <span class="muted mono">{{ $counts[$key] }}</span></a>
                    @endif
                @endforeach
            </nav>
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.courses.results.export', array_filter([$course, 'show' => $show])) }}">@include('admin.partials.icon', ['name' => 'upload']) Download for Excel</a>
            <span class="toolbar-count">{{ $tests->count() }} tests · practice tests left out</span>
        </div>

        @if ($shown->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'users'])</div>
                <h3>{{ $rows->isEmpty() ? 'No students yet' : 'Nobody here' }}</h3>
                <p>{{ $rows->isEmpty() ? 'Students appear here when they buy the course, or when you give them access.' : 'No student of this course is in this group right now.' }}</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data gradebook">
                    <thead>
                    <tr>
                        <th>Student</th>
                        <th>Progress</th>
                        @foreach ($tests as $test)
                            <th class="test" title="{{ $test->title }}">
                                <a href="{{ route('admin.results.index', ['quiz' => $test->id]) }}">{{ $labels[$test->id] }}</a>
                                <small>pass {{ $test->pass_percent }}%</small>
                            </th>
                        @endforeach
                        <th>Result</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($shown as $row)
                        @php($student = $row['enrollment']->user)
                        <tr>
                            <td>
                                <div class="row-title">
                                    <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                                    <div><a href="{{ route('admin.students.show', $student) }}">{{ $student->name }}</a><span class="row-sub">{{ $student->email }}</span></div>
                                </div>
                            </td>
                            <td style="min-width:110px">
                                <div class="mini-progress"><span style="width: {{ $row['progress']->percent }}%"></span></div>
                                <span class="row-sub">{{ $row['progress']->percent }}% · {{ $row['progress']->done }}/{{ $row['progress']->total }}</span>
                            </td>
                            @foreach ($tests as $test)
                                @php($score = $row['scores'][$test->id])
                                <td class="test">
                                    @if ($score)
                                        <a @class(['grade', 'grade-pass' => $score['passed'], 'grade-fail' => ! $score['passed']]) href="{{ route('admin.results.show', $score['best']) }}"
                                           title="{{ $test->title }}: best {{ $score['best']->percent }}%, {{ $score['passed'] ? 'passed' : 'not passed yet' }}, {{ $score['tries'] }} {{ $score['tries'] === 1 ? 'try' : 'tries' }}">{{ $score['best']->percent }}%</a>
                                        @if ($score['tries'] > 1)<small>{{ $score['tries'] }} tries</small>@endif
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <span class="status {{ $tones[$row['status']] }}">{{ $statuses[$row['status']] }}</span>
                                @if ($row['status'] === 'retake')
                                    <span class="row-sub" title="{{ $row['retake']->pluck('title')->implode(', ') }}">{{ $row['retake']->map(fn ($test) => $labels[$test->id])->implode(', ') }}</span>
                                @elseif ($row['enrollment']->certificate_code)
                                    <span class="row-sub mono">{{ $row['enrollment']->certificate_code }}</span>
                                @endif
                                @if ($row['asked'])
                                    <a class="row-sub" href="{{ route('admin.retake-requests.index') }}">Asked for a re-exam →</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
