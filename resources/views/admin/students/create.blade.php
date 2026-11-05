@extends('admin.layout')

@section('title', 'Add student')
@section('subtitle', 'A student account made by hand, e.g. someone who signed up at the office.')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.students.index') }}">Students</a>
    <span aria-hidden="true">/</span><span>Add</span>
@endsection

@section('content')
    <form method="post" data-main-form data-access-form action="{{ route('admin.students.store') }}">
        @csrf
        <div class="form-grid reveal">
            <div>
                <section class="card">
                    <div class="card-head"><div><h2>Student</h2><p>They sign in on the website with this email.</p></div></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'name', 'label' => 'Full name', 'value' => '', 'required' => true, 'attrs' => 'autocomplete="off" autofocus'])
                        @include('admin.fields.input', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => '', 'required' => true, 'attrs' => 'autocomplete="off"'])
                        @include('admin.fields.input', ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'value' => '', 'placeholder' => 'Optional, e.g. 012 345 678'])
                        @include('admin.fields.input', ['name' => 'password', 'label' => 'Password', 'value' => '', 'placeholder' => 'Leave empty to make one up', 'attrs' => 'autocomplete="new-password" minlength="8"', 'help' => 'At least 8 letters. A made-up password is shown once after saving, and emailed to the student.'])
                    </div>
                </section>
            </div>
            <div class="form-side">
                <section class="card">
                    <div class="card-head"><div><h2>Course</h2><p>Optional: open a course for them right away.</p></div></div>
                    <div class="card-body grant">
                        <div class="grant-step">
                            <select id="f-course_id" class="control" name="course_id" aria-label="Course" data-course-select>
                                <option value="">No course yet</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected((int) old('course_id') === $course->id)>{{ $course->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grant-step" data-needs-course>
                            <p class="grant-label"><span>2</span> Access</p>
                            @include('admin.students._access-length', ['planCourses' => $courses, 'formKey' => 'new'])
                        </div>
                        <div class="grant-step" data-needs-course>
                            <p class="grant-label"><span>3</span> Payment <small>shown on their invoice</small></p>
                            @include('admin.students._payment-fields')
                        </div>
                        @if ($classes->isNotEmpty())
                            <div class="grant-step">
                                <p class="grant-label">Class <small>optional</small></p>
                                <select class="control" name="classroom_id" aria-label="Class">
                                    <option value="">No class</option>
                                    @foreach ($classes as $option)
                                        <option value="{{ $option->id }}" @selected((int) old('classroom_id', request('class')) === $option->id)>{{ $option->name }}{{ $option->courses->isNotEmpty() ? ' · '.$option->courses->pluck('title')->implode(', ') : '' }}</option>
                                    @endforeach
                                </select>
                                <span class="help">Put them in a class, so its teacher follows them.</span>
                            </div>
                        @endif
                        <label class="check grant-mail"><input type="checkbox" name="send_email" value="1" checked> Email the student their sign-in (and the course link)</label>
                        <button class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'plus']) Add student</button>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
