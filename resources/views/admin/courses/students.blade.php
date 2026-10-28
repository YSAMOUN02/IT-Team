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

    <div class="form-grid">
        <section class="card">
            <div class="card-head"><div><h2>Students in this course</h2><p>{{ $rows->count() }} enrolled · {{ $rows->filter(fn ($r) => $r['enrollment']->completed_at)->count() }} completed</p></div></div>
            @if ($rows->isEmpty())
                <div class="empty-state">
                    <div class="ico">@include('admin.partials.icon', ['name' => 'users'])</div>
                    <h3>No students yet</h3>
                    <p>Students appear here when they buy the course, or when you give them access.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Student</th><th>Progress</th><th>Access</th><th>Source</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($rows as ['enrollment' => $enrollment, 'progress' => $progress])
                            <tr>
                                <td>
                                    <div class="row-title">
                                        <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($enrollment->user->name, 0, 1)) }}</span>
                                        <div>
                                            <a href="{{ $enrollment->user->isAdmin() ? route('admin.users.edit', $enrollment->user) : route('admin.students.show', $enrollment->user) }}">{{ $enrollment->user->name }}</a>
                                            <span class="row-sub">{{ $enrollment->user->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td style="min-width:160px">
                                    <div class="mini-progress"><span style="width: {{ $progress->percent }}%"></span></div>
                                    <span class="row-sub">{{ $progress->percent }}% @if ($enrollment->certificate_code)· certificate @endif</span>
                                </td>
                                <td>
                                    @if (! $enrollment->isActive())
                                        <span class="status status-off">Ended {{ $enrollment->expires_at?->format('d M Y') }}</span>
                                    @else
                                        <span class="status status-on">{{ $enrollment->expires_at ? 'Until '.$enrollment->expires_at->format('d M Y') : 'Lifetime' }}</span>
                                    @endif
                                </td>
                                <td class="muted">{{ ucfirst($enrollment->source) }}</td>
                                <td class="actions">
                                    <form method="post" action="{{ route('admin.enrollments.destroy', $enrollment) }}" data-confirm="Remove {{ $enrollment->user->name }}'s access to this course?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="form-side">
            <section class="card">
                <div class="card-head"><div><h2>Give access</h2><p>E.g. paid in cash at the office.</p></div></div>
                <form class="card-body fields" method="post" action="{{ route('admin.courses.students.grant', $course) }}">
                    @csrf
                    @include('admin.fields.input', ['name' => 'email', 'label' => 'Student email', 'type' => 'email', 'value' => '', 'required' => true, 'help' => 'The student must have registered on the website.'])
                    @php
                        $defaultDays = $course->plans->first()?->days;
                    @endphp
                    @include('admin.fields.input', ['name' => 'days', 'label' => 'Days of access', 'type' => 'number', 'value' => '', 'placeholder' => $defaultDays ?: 'lifetime', 'help' => 'Empty = the first plan’s period ('.($defaultDays ? $defaultDays.' days' : 'lifetime').').'])
                    <button class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Give access</button>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection
