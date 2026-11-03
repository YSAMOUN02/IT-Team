@extends('admin.layout')

@section('title', 'Add student')
@section('subtitle', 'A student account made by hand, e.g. someone who signed up at the office.')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.students.index') }}">Students</a>
    <span aria-hidden="true">/</span><span>Add</span>
@endsection

@section('content')
    <form method="post" data-main-form action="{{ route('admin.students.store') }}">
        @csrf
        <div class="form-grid reveal">
            <div>
                <section class="card">
                    <div class="card-head"><div><h2>Student</h2><p>They sign in on the website with this email.</p></div></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'name', 'label' => 'Full name', 'value' => '', 'required' => true])
                        @include('admin.fields.input', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => '', 'required' => true])
                        @include('admin.fields.input', ['name' => 'phone', 'label' => 'Phone', 'value' => ''])
                        @include('admin.fields.input', ['name' => 'password', 'label' => 'Password', 'value' => '', 'attrs' => 'autocomplete="new-password" minlength="8"', 'help' => 'Leave empty and one is made up for you; it is shown once after saving.'])
                    </div>
                </section>
            </div>
            <div class="form-side">
                <section class="card">
                    <div class="card-head"><div><h2>Course access</h2><p>Optional: open a course right away.</p></div></div>
                    <div class="card-body fields">
                        <div class="field">
                            <label class="field-label" for="f-course_id"><span>Course</span></label>
                            <select id="f-course_id" name="course_id">
                                <option value="">No course yet</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        @include('admin.fields.input', ['name' => 'days', 'label' => 'Days of access', 'type' => 'number', 'value' => '', 'placeholder' => 'first plan', 'help' => 'Empty = the course’s first plan period.'])
                        @include('admin.students._payment-fields')
                        <label class="check"><input type="checkbox" name="send_email" value="1" checked> Email the student (course link and sign-in)</label>
                        <button class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'plus']) Add student</button>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
