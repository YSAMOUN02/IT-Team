@extends('admin.layout')

@section('title', $student->name)
@section('subtitle', $student->email.($student->phone ? ' · '.$student->phone : '').' · joined '.$student->created_at->format('d M Y'))
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.students.index') }}">Students</a>
    <span aria-hidden="true">/</span><span>Student</span>
@endsection
@section('actions')
    <a class="btn btn-ghost" href="{{ route('admin.chats.show', $student) }}">@include('admin.partials.icon', ['name' => 'chat']) Chat</a>
    <a class="btn btn-ghost" href="{{ route('admin.chats.show', [$student, 'via' => 'email']) }}">@include('admin.partials.icon', ['name' => 'mail']) Email</a>
    <form method="post" action="{{ route('admin.students.update', $student) }}"
          @unless ($student->disabled_at) data-confirm="Turn off {{ $student->name }}'s account? They are signed out at once and cannot sign in or open any course until you turn it on again. Nothing is deleted." @endunless>
        @csrf @method('PATCH')
        <input type="hidden" name="disabled" value="{{ $student->disabled_at ? 0 : 1 }}">
        @if ($student->disabled_at)
            <button class="btn btn-primary">@include('admin.partials.icon', ['name' => 'check']) Turn account on</button>
        @else
            <button class="btn btn-danger">Turn account off</button>
        @endif
    </form>
    @if (count($student->device_sessions ?? []))
        <form method="post" action="{{ route('admin.students.sign-out', $student) }}" onsubmit="return confirm('Sign {{ e($student->name) }} out on every device? They can sign in again with their password.')">
            @csrf
            <button class="btn btn-ghost" title="Signed in on {{ count($student->device_sessions) }} device(s)">@include('admin.partials.icon', ['name' => 'logout']) Sign out everywhere ({{ count($student->device_sessions) }})</button>
        </form>
    @endif
@endsection

@section('content')
    @include('admin.students._new-login')
    @if ($student->disabled_at)
        <div class="alert alert-error" role="status">
            @include('admin.partials.icon', ['name' => 'alert'])
            <div><strong>This account is turned off</strong> (since {{ $student->disabled_at->format('d M Y, H:i') }}).
                {{ $student->name }} cannot sign in or open any course. Their courses, progress and orders are kept.</div>
        </div>
    @endif
    <div class="form-grid reveal">
        <div>
            <section class="card">
                <div class="card-head"><div><h2>Courses</h2><p>Access and progress. <b>Turn off</b> stops one course and can be undone; <b>Remove access</b> deletes it.</p></div></div>
                @if ($enrollments->isEmpty())
                    <div class="empty-state"><p>No courses yet.</p></div>
                @else
                    <div class="table-wrap">
                        <table class="data">
                            <thead><tr><th>Course</th><th>Progress</th><th>Access</th></tr></thead>
                            <tbody>
                            @foreach ($enrollments as ['enrollment' => $enrollment, 'progress' => $progress])
                                <tr>
                                    <td><a href="{{ route('admin.courses.curriculum', $enrollment->course) }}">{{ $enrollment->course->title }}</a>
                                        @include('admin.students._course-line')</td>
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

            <section class="card">
                <div class="card-head">
                    <div><h2>Tests & exams</h2><p>{{ $attemptCount ? 'Latest '.min(10, $attemptCount).' of '.$attemptCount.' tries. Open one to see each answer.' : 'Practice tests, quizzes and exams show here.' }}</p></div>
                    @if ($attemptCount > 10)<a href="{{ route('admin.results.index', ['q' => $student->email]) }}">See all</a>@endif
                </div>
                @if ($attempts->isEmpty())
                    <div class="empty-state"><p>No tests taken yet.</p></div>
                @else
                    <ul class="feed">
                        @foreach ($attempts as $attempt)
                            <li>
                                <div class="feed-main">
                                    <a class="feed-title" href="{{ route('admin.results.show', $attempt) }}">{{ $attempt->quiz->title }}</a>
                                    <span class="feed-meta"><b class="qtype qtype-{{ $attempt->quiz->type }}">{{ \App\Models\Quiz::TYPES[$attempt->quiz->type] }}</b> {{ $attempt->quiz->course?->title }} · {{ ($attempt->submitted_at ?? $attempt->started_at)->format('d M Y') }}</span>
                                </div>
                                @if ($attempt->isSubmitted())<b>{{ $attempt->percent }}%</b>@endif
                                @include('admin.results._status')
                            </li>
                        @endforeach
                    </ul>
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

            <section class="card">
                <div class="card-head"><div><h2>Classes</h2><p>Groups this student studies with.</p></div><a href="{{ route('admin.classes.index') }}">All classes</a></div>
                @if ($classes->isEmpty())
                    <div class="empty-state"><p>Not in a class yet.</p></div>
                @else
                    <ul class="feed">
                        @foreach ($classes as $class)
                            <li>
                                <div class="feed-main">
                                    <a class="feed-title" href="{{ route('admin.classes.show', $class) }}">{{ $class->name }}</a>
                                    <span class="feed-meta">{{ $class->courses->pluck('title')->implode(' · ') }}@if ($class->teacher) · {{ $class->teacher }}@endif @if ($class->dates()) · {{ $class->dates() }}@endif</span>
                                </div>
                                <span class="status {{ ['upcoming' => 'status-sched', 'running' => 'status-on', 'finished' => 'status-off'][$class->status()] }}">{{ \App\Models\Classroom::STATUSES[$class->status()] }}</span>
                                <form method="post" action="{{ route('admin.classes.students.destroy', [$class, $student]) }}"
                                      data-confirm="Take {{ $student->name }} out of “{{ $class->name }}”? Their course access and progress stay as they are.">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm btn-icon" title="Take out of the class" aria-label="Take out of {{ $class->name }}">@include('admin.partials.icon', ['name' => 'trash'])</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($otherClasses->isNotEmpty())
                    {{-- Put the student in another class without leaving this page. --}}
                    <form class="card-foot add-to-class" method="post" action="{{ route('admin.students.classes.store', $student) }}">
                        @csrf
                        <select class="control" name="classroom_id" aria-label="Class">
                            @foreach ($otherClasses as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}{{ $option->courses->isNotEmpty() ? ' · '.$option->courses->pluck('title')->implode(', ') : '' }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-ghost">@include('admin.partials.icon', ['name' => 'plus']) Add to class</button>
                    </form>
                @endif
            </section>

            <section class="card">
                <div class="card-head"><div><h2>Invoices</h2><p>The student downloads these from My account › Invoices.</p></div></div>
                @if ($invoices->isEmpty())
                    <div class="empty-state"><p>No invoices.</p></div>
                @else
                    <ul class="feed">
                        @foreach ($invoices as $invoice)
                            <li>
                                <div class="feed-main">
                                    <a class="feed-title mono" href="{{ route('admin.invoices.show', $invoice) }}" target="_blank">{{ $invoice->number }}</a>
                                    <span class="feed-meta">{{ $invoice->course_title }} · {{ $invoice->issued_at->format('d M Y') }} · {{ $invoice->methodLabel() }}</span>
                                </div>
                                <b>{{ $invoice->formattedAmount() }}</b>
                                <span class="status {{ $invoice->status === 'paid' ? 'status-on' : 'status-off' }}">{{ ucfirst($invoice->status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="form-side">
            <section class="card">
                <div class="card-head"><div><h2>Give access</h2><p>Paid in cash, by ABA, or a scholarship. Also turns a turned-off course back on.</p></div></div>
                <form class="card-body grant" method="post" action="{{ route('admin.students.grant', $student) }}" data-access-form>
                    @csrf
                    <div class="grant-step">
                        <p class="grant-label"><span>1</span> Course</p>
                        <select id="f-course" class="control" name="course_id" required aria-label="Course" data-course-select>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" @selected((int) old('course_id') === $course->id)>{{ $course->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grant-step">
                        <p class="grant-label"><span>2</span> Access</p>
                        @include('admin.students._access-length', ['planCourses' => $courses, 'formKey' => 'student'])
                    </div>
                    <div class="grant-step">
                        <p class="grant-label"><span>3</span> Payment <small>shown on their invoice</small></p>
                        @include('admin.students._payment-fields')
                    </div>
                    <label class="check grant-mail"><input type="checkbox" name="send_email" value="1" checked> Email the student the course link</label>
                    <button class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Give access to {{ $student->name }}</button>
                </form>
            </section>

            <section class="card">
                <div class="card-head"><div><h2>Give certificate</h2><p>E.g. they finished the course in class at the office.</p></div></div>
                <form class="card-body fields" method="post" action="{{ route('admin.students.certificates.store', $student) }}">
                    @csrf
                    @php
                        $certified = $enrollments->filter(fn ($row) => $row['enrollment']->certificate_code)->pluck('enrollment.course_id')->all();
                    @endphp
                    <div class="field">
                        <label class="field-label" for="f-cert-course"><span>Course</span></label>
                        <select id="f-cert-course" name="course_id" required>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" @disabled(in_array($course->id, $certified))>{{ $course->title }}{{ in_array($course->id, $certified) ? ' (has a certificate)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('admin.fields.input', ['name' => 'issued_on', 'label' => 'Date on the certificate', 'type' => 'date', 'value' => '', 'attrs' => 'max="'.now()->toDateString().'"',
                        'help' => 'Empty = today. Without access to the course, the student can still open and print the certificate.'])
                    <button class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Give certificate</button>
                </form>
            </section>
        </div>
    </div>
@endsection
