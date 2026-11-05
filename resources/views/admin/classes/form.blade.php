@extends('admin.layout')

@php
    $ticked = array_map('intval', (array) old('courses', $chosen));
    $coursesError = $errors->first('courses') ?: $errors->first('courses.*');
@endphp

@section('title', $classroom->exists ? 'Edit class' : 'New class')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.classes.index') }}">Classes</a>
    @if ($classroom->exists)<span aria-hidden="true">/</span><a href="{{ route('admin.classes.show', $classroom) }}">{{ $classroom->name }}</a>@endif
    <span aria-hidden="true">/</span><span>{{ $classroom->exists ? 'Edit' : 'New' }}</span>
@endsection

@section('content')
    <form method="post" data-main-form action="{{ $classroom->exists ? route('admin.classes.update', $classroom) : route('admin.classes.store') }}">
        @csrf
        @if ($classroom->exists) @method('PUT') @endif
        <div class="form-grid reveal">
            <section class="card">
                <div class="card-head"><div><h2>Class</h2><p>A group of students taking one or more courses together.</p></div></div>
                <div class="card-body fields">
                    @include('admin.fields.input', ['name' => 'name', 'label' => 'Name', 'value' => $classroom->name, 'required' => true, 'max' => 120,
                        'placeholder' => 'e.g. Excel weekend class, November', 'attrs' => $classroom->exists ? '' : 'autofocus'])

                    <div @class(['field', 'has-error' => $coursesError])>
                        <span class="field-label"><span>Courses<em>*</em></span></span>
                        <div class="pill-choice course-ticks" role="group" aria-label="Courses">
                            @foreach ($courses as $course)
                                <label><input type="checkbox" name="courses[]" value="{{ $course->id }}" @checked(in_array($course->id, $ticked, true))><span>{{ $course->title }}</span></label>
                            @endforeach
                        </div>
                        @if ($coursesError)
                            <span class="error-text">{{ $coursesError }}</span>
                        @else
                            <span class="help">Tick every course this class studies. The class page shows the students’ progress in each.</span>
                        @endif
                    </div>

                    @include('admin.fields.input', ['name' => 'teacher', 'label' => 'Teacher', 'value' => $classroom->teacher, 'placeholder' => 'Optional, e.g. Mang Seyha'])
                    @include('admin.fields.input', ['name' => 'schedule', 'label' => 'Schedule', 'value' => $classroom->schedule, 'placeholder' => 'Optional, e.g. Sat & Sun 8:00–11:00, room 2'])
                    <div class="cols-2">
                        @include('admin.fields.input', ['name' => 'starts_on', 'label' => 'First day', 'type' => 'date', 'value' => $classroom->starts_on?->format('Y-m-d')])
                        @include('admin.fields.input', ['name' => 'ends_on', 'label' => 'Last day', 'type' => 'date', 'value' => $classroom->ends_on?->format('Y-m-d')])
                    </div>
                    @include('admin.fields.textarea', ['name' => 'notes', 'label' => 'Notes for teachers', 'value' => $classroom->notes, 'rows' => 4, 'help' => 'Only admins see these.'])
                </div>
            </section>

            <div class="form-side">
                <section class="card">
                    <div class="card-body" style="display:grid;gap:10px">
                        <button class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) {{ $classroom->exists ? 'Save class' : 'Create class' }}</button>
                        <p class="help" style="margin:0">{{ $classroom->exists ? 'Students and their course access stay as they are.' : 'Next you add the students.' }}</p>
                    </div>
                </section>
            </div>
        </div>
    </form>

    @if ($classroom->exists)
        <form method="post" action="{{ route('admin.classes.destroy', $classroom) }}" class="danger-zone reveal"
              data-confirm="Delete the class “{{ $classroom->name }}”? Its students and their course access are not changed.">
            @csrf @method('DELETE')
            <button class="btn btn-danger">@include('admin.partials.icon', ['name' => 'trash']) Delete class</button>
        </form>
    @endif
@endsection
