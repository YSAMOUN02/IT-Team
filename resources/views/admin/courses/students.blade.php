@extends('admin.layout')

@section('title', $course->title.' · Students')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.courses.index') }}">Courses</a>
    <span aria-hidden="true">/</span><span>Students</span>
@endsection
@section('hide_head', true)

@section('content')
<div class="reveal">
    @include('admin.courses._workspace', ['course' => $course, 'tab' => 'students'])
    @include('admin.students._new-login')

    <div class="form-grid">
        <section class="card">
            <div class="card-head"><div><h2>Students in this course</h2><p>{{ $rows->count() }} enrolled{{ $class ? ' in this class' : '' }} · {{ $rows->filter(fn ($r) => $r['enrollment']->isActive())->count() }} can open it now · {{ $rows->filter(fn ($r) => $r['enrollment']->disabled_at)->count() }} turned off · {{ $rows->filter(fn ($r) => $r['enrollment']->completed_at)->count() }} completed</p></div></div>
            @if ($classes->isNotEmpty())
                <form class="toolbar" method="get">
                    @include('admin.classes._filter', ['filterClasses' => $classes, 'filterClass' => $class?->id])
                    @if ($class)
                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.classes.show', [$class, 'course' => $course->id]) }}">@include('admin.partials.icon', ['name' => 'grid']) Open the class</a>
                    @endif
                </form>
            @endif
            @if ($rows->isEmpty())
                <div class="empty-state">
                    <div class="ico">@include('admin.partials.icon', ['name' => 'users'])</div>
                    @if ($class)
                        <h3>Nobody in “{{ $class->name }}” has this course yet</h3>
                        <p>Give them access with the box on the right, or <a href="{{ route('admin.courses.students', $course) }}">show every student</a>.</p>
                    @else
                        <h3>No students yet</h3>
                        <p>Students appear here when they buy the course, or when you give them access.</p>
                    @endif
                </div>
            @else
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Student</th><th>Progress</th><th>Access</th></tr></thead>
                        <tbody>
                        @foreach ($rows as ['enrollment' => $enrollment, 'progress' => $progress])
                            <tr>
                                <td>
                                    <div class="row-title">
                                        <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($enrollment->user->name, 0, 1)) }}</span>
                                        <div>
                                            <a href="{{ $enrollment->user->isAdmin() ? route('admin.users.edit', $enrollment->user) : route('admin.students.show', $enrollment->user) }}">{{ $enrollment->user->name }}</a>
                                            @if ($enrollment->user->isDisabled())<span class="status status-blocked">Account off</span>@endif
                                            <span class="row-sub">{{ $enrollment->user->email }}</span>
                                            @include('admin.students._course-line')
                                            @include('admin.classes._chips', ['chipClasses' => $studentClasses[$enrollment->user_id] ?? collect()])
                                        </div>
                                    </div>
                                </td>
                                <td style="min-width:130px">
                                    <div class="mini-progress"><span style="width: {{ $progress->percent }}%"></span></div>
                                    <span class="row-sub">{{ $progress->percent }}% · {{ $progress->done }}/{{ $progress->total }}</span>
                                </td>
                                <td>@include('admin.students._access') @include('admin.students._access-actions')</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="form-side">
            @include('admin.courses._give-access')
        </div>
    </div>
</div>
@endsection
