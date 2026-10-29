@extends('admin.layout')

@php
    $typeHelp = [
        'practice' => 'Students check each answer straight away and see the explanation. Unlimited, untimed, not graded.',
        'quiz' => 'End-of-module quiz. Must be passed for the course to count as completed.',
        'mock' => 'Timed rehearsal of the final exam. Unlimited tries; does not count toward completion.',
        'final' => 'Timed final exam. Must be passed to get the certificate.',
    ];
@endphp

@section('title', $quiz->exists ? $quiz->title : 'New '.strtolower(\App\Models\Quiz::TYPES[$quiz->type]))
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.courses.index') }}">Courses</a>
    <span aria-hidden="true">/</span><a href="{{ route('admin.courses.curriculum', $course) }}">{{ \Illuminate\Support\Str::limit($course->title, 30) }}</a>
    <span aria-hidden="true">/</span><span>{{ \App\Models\Quiz::TYPES[$quiz->type] }}</span>
@endsection
@section('actions')
    <a class="btn btn-ghost" href="{{ route('admin.courses.curriculum', $course) }}">← Curriculum</a>
    @if ($quiz->exists)
        <a class="btn btn-ghost" href="{{ route('learn.quiz', [$course->slug, $quiz->slug]) }}" target="_blank" rel="noopener">@include('admin.partials.icon', ['name' => 'user']) View as student</a>
        <form method="post" action="{{ route('admin.quizzes.destroy', $quiz) }}" data-confirm="Delete “{{ $quiz->title }}” with all its questions and student results?">
            @csrf @method('DELETE')
            <button class="btn btn-danger">@include('admin.partials.icon', ['name' => 'trash']) Delete</button>
        </form>
    @endif
@endsection

@section('content')
<div class="reveal">
    @if ($quiz->exists && isset($stats))
        <div class="kpis kpis-mini">
            <div class="card kpi"><div class="kpi-label">Questions</div><div class="kpi-value">{{ $quiz->questions->count() }}</div></div>
            <a class="card kpi" href="{{ route('admin.results.index', ['quiz' => $quiz->id]) }}" title="See each student's result"><div class="kpi-label">Attempts</div><div class="kpi-value">{{ $stats['attempts'] }}</div><div class="kpi-note">{{ $stats['students'] }} students · see results →</div></a>
            <div class="card kpi"><div class="kpi-label">Average score</div><div class="kpi-value">{{ $stats['attempts'] ? $stats['average'].'%' : '—' }}</div></div>
            <div class="card kpi"><div class="kpi-label">Pass rate</div><div class="kpi-value">{{ $stats['pass_rate'] !== null ? $stats['pass_rate'].'%' : '—' }}</div></div>
        </div>
    @endif

    <form method="post" data-main-form action="{{ $quiz->exists ? route('admin.quizzes.update', $quiz) : route('admin.courses.quizzes.store', $course) }}">
        @csrf
        @if ($quiz->exists) @method('PUT') @endif

        <div class="form-grid">
            <div>
                <section class="card">
                    <div class="card-head"><div><h2>Test settings</h2><p>{{ $typeHelp[$quiz->type] }}</p></div></div>
                    <div class="card-body fields">
                        <div class="field">
                            <span class="field-label"><span>Type</span></span>
                            <div class="type-picker">
                                @foreach (\App\Models\Quiz::TYPES as $value => $label)
                                    <label>
                                        <input type="radio" name="type" value="{{ $value }}" @checked(old('type', $quiz->type) === $value)>
                                        <span><b class="qtype qtype-{{ $value }}">{{ $label }}</b><small>{{ $typeHelp[$value] }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @include('admin.fields.input', ['name' => 'title', 'label' => 'Title', 'value' => $quiz->title, 'required' => true, 'attrs' => 'data-slug-source', 'placeholder' => 'Module 1 quiz'])
                        @include('admin.fields.textarea', ['name' => 'description', 'label' => 'Instructions for students', 'value' => $quiz->description, 'rows' => 3])
                        <div class="cols-2">
                            <div class="field">
                                <label class="field-label" for="f-module"><span>Module</span></label>
                                <select id="f-module" name="course_module_id">
                                    <option value="">— Not in a module —</option>
                                    @foreach ($course->modules as $module)
                                        <option value="{{ $module->id }}" @selected(old('course_module_id', $quiz->course_module_id) == $module->id)>{{ $loop->iteration }}. {{ $module->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @include('admin.fields.input', ['name' => 'slug', 'label' => 'Web address', 'value' => $quiz->slug, 'attrs' => 'data-slug'])
                        </div>
                        <div class="cols-3">
                            @include('admin.fields.input', ['name' => 'time_limit', 'label' => 'Time limit (min)', 'type' => 'number', 'value' => $quiz->time_limit, 'placeholder' => 'none', 'attrs' => 'min="1"'])
                            @include('admin.fields.input', ['name' => 'pass_percent', 'label' => 'Pass mark (%)', 'type' => 'number', 'value' => $quiz->pass_percent, 'required' => true, 'attrs' => 'min="1" max="100"'])
                            @include('admin.fields.input', ['name' => 'max_attempts', 'label' => 'Max attempts', 'type' => 'number', 'value' => $quiz->max_attempts, 'placeholder' => 'unlimited', 'attrs' => 'min="1"'])
                        </div>
                        <span class="help">Practice tests ignore the time limit and attempts.</span>
                    </div>
                </section>
            </div>

            <div class="form-side">
                <section class="card">
                    <div class="card-head"><h2>Options</h2></div>
                    <div class="card-body fields">
                        <label class="switch">
                            <span class="switch-text"><strong>Visible to students</strong></span>
                            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $quiz->is_published ?? true))>
                            <span class="switch-ui" aria-hidden="true"></span>
                        </label>
                        <label class="switch">
                            <span class="switch-text"><strong>Shuffle questions</strong><span>Different order for each attempt.</span></span>
                            <input type="checkbox" name="shuffle_questions" value="1" @checked(old('shuffle_questions', $quiz->shuffle_questions))>
                            <span class="switch-ui" aria-hidden="true"></span>
                        </label>
                        <label class="switch">
                            <span class="switch-text"><strong>Show correct answers</strong><span>After submitting, with explanations.</span></span>
                            <input type="checkbox" name="show_answers" value="1" @checked(old('show_answers', $quiz->show_answers ?? true))>
                            <span class="switch-ui" aria-hidden="true"></span>
                        </label>
                        <label class="switch">
                            <span class="switch-text"><strong>Free preview</strong><span>Signed-in visitors can try it before buying.</span></span>
                            <input type="checkbox" name="is_preview" value="1" @checked(old('is_preview', $quiz->is_preview))>
                            <span class="switch-ui" aria-hidden="true"></span>
                        </label>
                    </div>
                    <div class="card-foot save-bar">
                        <button type="submit" class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Save settings</button>
                    </div>
                </section>
            </div>
        </div>
    </form>

    @if ($quiz->exists)
        <section class="card" style="margin-top:20px">
            <div class="card-head">
                <div><h2>Questions</h2><p>{{ $quiz->questions->count() }} questions · {{ $quiz->questions->sum('points') }} points</p></div>
                <div class="page-actions">
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.quizzes.questions.create', [$quiz, 'type' => 'true_false']) }}">@include('admin.partials.icon', ['name' => 'plus']) True / False</a>
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.quizzes.questions.create', [$quiz, 'type' => 'multiple']) }}">@include('admin.partials.icon', ['name' => 'plus']) Several answers</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('admin.quizzes.questions.create', [$quiz, 'type' => 'single']) }}">@include('admin.partials.icon', ['name' => 'plus']) Question</a>
                </div>
            </div>
            @if ($quiz->questions->isEmpty())
                <div class="empty-state">
                    <div class="ico">@include('admin.partials.icon', ['name' => 'pages'])</div>
                    <h3>No questions yet</h3>
                    <p>Students cannot start this test until it has questions.</p>
                </div>
            @else
                <ol class="question-list">
                    @foreach ($quiz->questions as $question)
                        <li>
                            <span class="q-index">{{ $loop->iteration }}</span>
                            <div class="q-body">
                                <a href="{{ route('admin.questions.edit', $question) }}">{{ \Illuminate\Support\Str::limit($question->prompt, 160) }}</a>
                                <ul class="q-opts">
                                    @foreach ($question->options as $option)
                                        <li @class(['ok' => $option->is_correct])>{{ $option->is_correct ? '✓' : '·' }} {{ \Illuminate\Support\Str::limit($option->text, 60) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <span class="muted mono" style="white-space:nowrap">{{ \App\Models\QuizQuestion::TYPES[$question->type] }} · {{ $question->points }} pt</span>
                            <div class="row-tools">
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.questions.edit', $question) }}">@include('admin.partials.icon', ['name' => 'edit']) Edit</a>
                                <form method="post" action="{{ route('admin.questions.destroy', $question) }}" data-confirm="Delete question {{ $loop->iteration }}?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm btn-icon" title="Delete" aria-label="Delete">@include('admin.partials.icon', ['name' => 'trash'])</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
</div>
@endsection
