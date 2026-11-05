@extends('admin.layout')

@php
    $statusTone = ['upcoming' => 'status-sched', 'running' => 'status-on', 'finished' => 'status-off'][$classroom->status()];
    $tones = ['learning' => 'status-sched', 'completed' => 'status-on', 'retake' => 'status-blocked', 'not_started' => 'status-off', 'ended' => 'status-blocked', 'no_access' => 'status-blocked'];
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/u', trim($name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: '?';
    $total = $rows->count();
    $empty = $total === 0;
    $several = $courses->count() > 1;
    $quietDays = \App\Http\Controllers\Admin\ClassroomController::QUIET_DAYS;
    // Links on this page keep the course tab; the filter tab is set per link.
    $here = fn (array $change = []) => route('admin.classes.show', array_filter([$classroom, 'course' => $several ? $course?->id : null] + $change));
    // "Give access →": the course's Give access box, with this student already picked.
    $giveAccess = fn ($courseToGive, $student) => route('admin.courses.students', [$courseToGive, 'email' => $student->email, 'class' => $classroom->id]).'#give-access';
@endphp

@section('title', $classroom->name)
@section('subtitle')
    {{ $courses->pluck('title')->implode(' · ') ?: 'No course yet' }}@if ($classroom->teacher) · Teacher: {{ $classroom->teacher }}@endif @if ($classroom->dates()) · {{ $classroom->dates() }}@endif @if ($classroom->schedule) · {{ $classroom->schedule }}@endif
@endsection
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.classes.index') }}">Classes</a>
    <span aria-hidden="true">/</span><span>Class</span>
@endsection
@section('actions')
    <span class="status {{ $statusTone }}">{{ \App\Models\Classroom::STATUSES[$classroom->status()] }}</span>
    @unless ($empty)
        <a class="btn btn-ghost" href="{{ route('admin.classes.export', array_filter([$classroom, 'course' => $several ? $course?->id : null])) }}">@include('admin.partials.icon', ['name' => 'upload']) Download Excel</a>
    @endunless
    <a class="btn btn-ghost" href="{{ route('admin.classes.edit', $classroom) }}">@include('admin.partials.icon', ['name' => 'edit']) Edit</a>
@endsection

@section('content')
<div class="reveal">
    @if ($several)
        <nav class="segmented class-tabs" aria-label="Course">
            <a href="{{ route('admin.classes.show', $classroom) }}" @class(['on' => ! $course]) @if (! $course) aria-current="page" @endif>Overview</a>
            @foreach ($courses as $tabCourse)
                <a href="{{ route('admin.classes.show', [$classroom, 'course' => $tabCourse->id]) }}" @class(['on' => $course?->id === $tabCourse->id]) @if ($course?->id === $tabCourse->id) aria-current="page" @endif>{{ $tabCourse->title }}</a>
            @endforeach
        </nav>
    @endif

    @if ($courses->isEmpty())
        <div class="alert alert-error" role="status">@include('admin.partials.icon', ['name' => 'alert']) This class has no course. <a href="{{ route('admin.classes.edit', $classroom) }}">Choose its courses</a>.</div>
    @endif

    @unless ($empty)
        <section class="kpis kpis-mini track-kpis" aria-label="Summary">
            <div class="card kpi"><div class="kpi-label">Students</div><div class="kpi-value">{{ $total }}</div><div class="kpi-note">{{ $counts['learning'] }} learning · {{ $counts['completed'] }} completed</div></div>
            <div class="card kpi"><div class="kpi-label">Average progress</div><div class="kpi-value">{{ $average !== null ? round($average).'%' : '—' }}</div><div class="kpi-note">{{ $course ? 'of the course done' : 'across the '.$courses->count().' courses' }}</div></div>
            @if ($course)
                <div class="card kpi"><div class="kpi-label">Completed</div><div class="kpi-value">{{ $completions }}</div><div class="kpi-note">{{ $total ? round($completions * 100 / $total) : 0 }}% of the class</div></div>
            @else
                <div class="card kpi"><div class="kpi-label">Courses completed</div><div class="kpi-value">{{ $completions }}<span class="muted" style="font-size:16px"> / {{ $pairs }}</span></div><div class="kpi-note">student × course</div></div>
            @endif
            <a @class(['card', 'kpi', 'kpi-alert' => $counts['help']]) href="{{ $here(['show' => 'help']) }}#students">
                <div class="kpi-label">Need help</div><div class="kpi-value">{{ $counts['help'] }}</div>
                <div class="kpi-note">failed a test, quiet {{ $quietDays }}+ days, or no access</div>
            </a>
        </section>
    @endunless

    {{-- Add students: open when the class is empty --}}
    <details class="card add-students" @if ($empty || $errors->has('students')) open @endif data-add-students>
        <summary class="card-head">
            <div><h2>@include('admin.partials.icon', ['name' => 'plus']) Add students</h2><p>{{ $pickable->count() }} {{ str('student')->plural($pickable->count()) }} not in this class yet. Those who have its courses are listed first.</p></div>
        </summary>
        <form class="card-body" method="post" action="{{ route('admin.classes.students.store', $classroom) }}">
            @csrf
            @if ($pickable->isEmpty())
                <p class="help" style="margin:0">Every student is already in this class. New students: <a href="{{ route('admin.students.create', ['class' => $classroom->id]) }}">Add student</a>.</p>
            @else
                <div class="picker">
                    <div class="picker-search">
                        @include('admin.partials.icon', ['name' => 'search'])
                        <input type="search" placeholder="Search name, email or phone…" autocomplete="off" aria-label="Search students" data-pick-search>
                    </div>
                    <ul class="picker-list pick-many" data-pick-list>
                        @foreach ($pickable as $student)
                            <li data-pick-row data-search="{{ mb_strtolower($student->name.' '.$student->email.' '.$student->phone) }}">
                                <label class="picker-item">
                                    <input type="checkbox" name="students[]" value="{{ $student->id }}" @checked(in_array($student->id, (array) old('students', [])))>
                                    <span class="avatar" aria-hidden="true">{{ $initials($student->name) }}</span>
                                    <span class="picker-text"><b>{{ $student->name }}</b><small>{{ $student->email }}{{ $student->phone ? ' · '.$student->phone : '' }}</small></span>
                                    @if ($courses->isNotEmpty() && $student->courses_had >= $courses->count())
                                        <span class="picker-tag picker-tag-ok">{{ $several ? 'Has all '.$courses->count().' courses' : 'Has the course' }}</span>
                                    @elseif ($student->courses_had)
                                        <span class="picker-tag picker-tag-ok">Has {{ $student->courses_had }} of {{ $courses->count() }}</span>
                                    @else
                                        <span class="picker-tag">No access yet</span>
                                    @endif
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <div class="picker-empty" data-pick-empty hidden><p>No student matches.</p></div>
                </div>
                @error('students')<span class="error-text">{{ $message }}</span>@enderror
                <div class="add-actions">
                    <button type="button" class="link-btn" data-pick-all>Tick all shown</button>
                    <span class="help">Being in a class does not open its courses: use <b>Give access →</b> next to a student, or the course’s Students page. New student? <a href="{{ route('admin.students.create', ['class' => $classroom->id]) }}">Add student</a>.</span>
                    <button class="btn btn-primary" data-pick-submit disabled>@include('admin.partials.icon', ['name' => 'plus']) <span data-pick-label>Add students</span></button>
                </div>
            @endif
        </form>
    </details>

    @unless ($empty)
        <section class="card" id="students">
            <div class="toolbar">
                <nav class="segmented" aria-label="Show">
                    <a href="{{ $here() }}#students" @class(['on' => ! $show])>All <span class="muted mono">{{ $total }}</span></a>
                    @foreach (\App\Http\Controllers\Admin\ClassroomController::VIEWS as $key => $label)
                        @if ($counts[$key])
                            <a href="{{ $here(['show' => $key]) }}#students" @class(['on' => $show === $key, 'seg-alert' => $key === 'help'])>{{ $label }} <span class="muted mono">{{ $counts[$key] }}</span></a>
                        @endif
                    @endforeach
                </nav>
                @if ($course)
                    <span class="toolbar-links">
                        <a href="{{ route('admin.courses.students', [$course, 'class' => $classroom->id]) }}">Course students</a> ·
                        <a href="{{ route('admin.courses.results', [$course, 'class' => $classroom->id]) }}">Results</a> ·
                        <a href="{{ route('admin.results.index', ['course' => $course->id, 'class' => $classroom->id]) }}">Every try</a>
                    </span>
                    <span class="toolbar-count">{{ $tests->count() }} tests · practice left out</span>
                @else
                    <span class="toolbar-count">Click a course to see its tests</span>
                @endif
            </div>

            @if ($shown->isEmpty())
                <div class="empty-state"><p>Nobody in this group right now.</p></div>
            @elseif ($course)
                @include('admin.classes._course-table')
            @else
                @include('admin.classes._overview-table')
            @endif
        </section>
    @endunless

    @if ($classroom->notes)
        <section class="card">
            <div class="card-head"><div><h2>Notes for teachers</h2><p>Only admins see these.</p></div></div>
            <div class="card-body"><div class="message-body">{{ $classroom->notes }}</div></div>
        </section>
    @endif

    <p class="muted analytics-note">
        “Needs help”: did not pass a module quiz or the final, has finished nothing for {{ $quietDays }} days (students who joined in the last {{ $quietDays }} days get time first), or cannot open a course of the class.
        Scores are each student’s best try. Click a score to see the answers.
    </p>
</div>
@endsection
