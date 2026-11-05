@extends('admin.layout')

@php
    $tones = ['upcoming' => 'status-sched', 'running' => 'status-on', 'finished' => 'status-off'];
@endphp

@section('title', 'Classes')
@section('subtitle', 'Students who study together, in one course or several. Open a class to follow each student’s progress and tests.')
@section('crumbs')<span aria-hidden="true">/</span><span>Sales</span><span aria-hidden="true">/</span><span>Classes</span>@endsection
@section('actions')
    <a class="btn btn-primary" href="{{ route('admin.classes.create', array_filter(['course' => $courseId])) }}">@include('admin.partials.icon', ['name' => 'plus']) New class</a>
@endsection

@section('content')
    <div class="card reveal">
        @if ($total === 0 && ! $courseId)
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'grid'])</div>
                <h3>No classes yet</h3>
                <p>Make a class for each group you teach (for example “Excel weekend class, November”), choose its courses, add its students,<br>and see how each of them is doing on one page.</p>
                <a class="btn btn-primary" href="{{ route('admin.classes.create') }}">@include('admin.partials.icon', ['name' => 'plus']) New class</a>
            </div>
        @else
            <form class="toolbar" method="get">
                <nav class="segmented" aria-label="Show">
                    <a href="{{ route('admin.classes.index', array_filter(['course' => $courseId])) }}" @class(['on' => ! $show])>All <span class="muted mono">{{ $total }}</span></a>
                    @foreach (\App\Models\Classroom::STATUSES as $key => $label)
                        @if ($counts[$key] ?? 0)
                            <a href="{{ route('admin.classes.index', array_filter(['show' => $key, 'course' => $courseId])) }}" @class(['on' => $show === $key])>{{ $label }} <span class="muted mono">{{ $counts[$key] }}</span></a>
                        @endif
                    @endforeach
                </nav>
                @if ($show)<input type="hidden" name="show" value="{{ $show }}">@endif
                <select name="course" aria-label="Course" onchange="this.form.submit()">
                    <option value="">All courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected($courseId === $course->id)>{{ $course->title }}</option>
                    @endforeach
                </select>
            </form>

            @if ($classes->isEmpty())
                <div class="empty-state"><p>No class matches.</p></div>
            @else
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Class</th><th class="hide-sm">When</th><th class="num">Students</th><th class="hide-sm">Status</th></tr></thead>
                        <tbody>
                        @foreach ($classes as $class)
                            <tr>
                                <td>
                                    <div class="row-title">
                                        @php $firstCourse = $class->courses->first(); @endphp
                                        @if ($firstCourse?->image)
                                            <img class="thumb" src="{{ $firstCourse->mediaUrl() }}" alt="">
                                        @else
                                            <span class="thumb">@include('admin.partials.icon', ['name' => 'grid'])</span>
                                        @endif
                                        <div>
                                            <a href="{{ route('admin.classes.show', $class) }}">{{ $class->name }}</a>
                                            <span class="row-sub">{{ $class->courses->pluck('title')->implode(' · ') ?: 'No course' }}@if ($class->teacher) · Teacher: {{ $class->teacher }}@endif</span>
                                            <span class="row-sub show-sm">{{ \App\Models\Classroom::STATUSES[$class->status()] }}@if ($class->dates()) · {{ $class->dates() }}@endif</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="hide-sm">{{ $class->dates() ?? '—' }}@if ($class->schedule)<span class="row-sub">{{ $class->schedule }}</span>@endif</td>
                                <td class="num"><b>{{ $class->students_count }}</b></td>
                                <td class="hide-sm"><span class="status {{ $tones[$class->status()] }}">{{ \App\Models\Classroom::STATUSES[$class->status()] }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
@endsection
