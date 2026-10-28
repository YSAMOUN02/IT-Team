@extends('admin.layout')

@section('title', $student->name)
@section('subtitle', $student->email.($student->phone ? ' · '.$student->phone : '').' · joined '.$student->created_at->format('d M Y'))
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.students.index') }}">Students</a>
    <span aria-hidden="true">/</span><span>Student</span>
@endsection
@section('actions')
    <a class="btn btn-ghost" href="mailto:{{ $student->email }}">@include('admin.partials.icon', ['name' => 'mail']) Email</a>
    @if (count($student->device_sessions ?? []))
        <form method="post" action="{{ route('admin.students.sign-out', $student) }}" onsubmit="return confirm('Sign {{ e($student->name) }} out on every device? They can sign in again with their password.')">
            @csrf
            <button class="btn btn-ghost" title="Signed in on {{ count($student->device_sessions) }} device(s)">@include('admin.partials.icon', ['name' => 'logout']) Sign out everywhere ({{ count($student->device_sessions) }})</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="form-grid reveal">
        <div>
            <section class="card">
                <div class="card-head"><div><h2>Courses</h2><p>Access and progress.</p></div></div>
                @if ($enrollments->isEmpty())
                    <div class="empty-state"><p>No courses yet.</p></div>
                @else
                    <div class="table-wrap">
                        <table class="data">
                            <thead><tr><th>Course</th><th>Progress</th><th>Access</th><th>Source</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($enrollments as ['enrollment' => $enrollment, 'progress' => $progress])
                                <tr>
                                    <td><a href="{{ route('admin.courses.curriculum', $enrollment->course) }}">{{ $enrollment->course->title }}</a>
                                        @if ($enrollment->certificate_code)<span class="row-sub">Certificate {{ $enrollment->certificate_code }}</span>@endif</td>
                                    <td style="min-width:150px">
                                        <div class="mini-progress"><span style="width: {{ $progress->percent }}%"></span></div>
                                        <span class="row-sub">{{ $progress->percent }}% · {{ $progress->done }}/{{ $progress->total }}</span>
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
                                        <form method="post" action="{{ route('admin.enrollments.destroy', $enrollment) }}" data-confirm="Remove {{ $student->name }}'s access to {{ $enrollment->course->title }}? Their progress is kept if you give access again.">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm">Remove access</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card">
                <div class="card-head"><h2>Orders</h2></div>
                @if ($orders->isEmpty())
                    <div class="empty-state"><p>No orders.</p></div>
                @else
                    <ul class="feed">
                        @foreach ($orders as $order)
                            <li>
                                <div class="feed-main">
                                    <a class="feed-title mono" href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a>
                                    <span class="feed-meta">{{ $order->course_title }} · {{ $order->created_at->format('d M Y') }}</span>
                                </div>
                                <b>{{ $order->formattedAmount() }}</b>
                                <span class="status {{ $order->isPaid() ? 'status-on' : 'status-off' }}">{{ \App\Models\Order::STATUSES[$order->status] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="form-side">
            <section class="card">
                <div class="card-head"><div><h2>Give access</h2><p>E.g. paid in cash or a scholarship.</p></div></div>
                <form class="card-body fields" method="post" action="{{ route('admin.students.grant', $student) }}">
                    @csrf
                    <div class="field">
                        <label class="field-label" for="f-course"><span>Course</span></label>
                        <select id="f-course" name="course_id" required>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}">{{ $course->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('admin.fields.input', ['name' => 'days', 'label' => 'Days of access', 'type' => 'number', 'value' => '', 'placeholder' => 'lifetime', 'help' => 'Empty = lifetime. Adds to any access that is still running.'])
                    <button class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Give access</button>
                </form>
            </section>
        </div>
    </div>
@endsection
