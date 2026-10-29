@extends('admin.layout')

@php
    $type = old('type', $question->type);
    $rows = old('options') ? collect(old('options'))->map(fn ($o, $k) => ['key' => $k, 'text' => $o['text'] ?? '', 'correct' => in_array((string) $k, array_map('strval', (array) old('correct', [])), true)])->values()
        // Saved answers are keyed by their id, so saving updates them in place (students' past answers point at these ids).
        : ($options->isNotEmpty() ? $options->map(fn ($o) => ['key' => $o->id, 'text' => $o->text, 'correct' => $o->is_correct])->values()
        : collect(range(0, 3))->map(fn ($i) => ['key' => $i, 'text' => '', 'correct' => false]));
    $tf = old('tf_answer', $question->exists && $type === 'true_false' ? ($options->first()?->is_correct ? 'true' : 'false') : null);
@endphp

@section('title', $question->exists ? 'Edit question' : 'New question')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.courses.curriculum', $quiz->course) }}">{{ \Illuminate\Support\Str::limit($quiz->course->title, 24) }}</a>
    <span aria-hidden="true">/</span><a href="{{ route('admin.quizzes.edit', $quiz) }}">{{ \Illuminate\Support\Str::limit($quiz->title, 30) }}</a>
    <span aria-hidden="true">/</span><span>Question</span>
@endsection
@section('actions')
    <a class="btn btn-ghost" href="{{ route('admin.quizzes.edit', $quiz) }}">← {{ \Illuminate\Support\Str::limit($quiz->title, 30) }}</a>
@endsection

@section('content')
    <form method="post" enctype="multipart/form-data" data-main-form
          action="{{ $question->exists ? route('admin.questions.update', $question) : route('admin.quizzes.questions.store', $quiz) }}">
        @csrf
        @if ($question->exists) @method('PUT') @endif

        <div class="form-grid reveal">
            <div>
                <section class="card">
                    <div class="card-head"><h2>Question</h2></div>
                    <div class="card-body fields">
                        <div class="field">
                            <span class="field-label"><span>Answer type</span></span>
                            <div class="segmented" role="radiogroup">
                                @foreach (\App\Models\QuizQuestion::TYPES as $value => $label)
                                    <label class="seg"><input type="radio" name="type" value="{{ $value }}" @checked($type === $value) data-qtype> <span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                        </div>
                        @include('admin.fields.textarea', ['name' => 'prompt', 'label' => 'Question', 'value' => $question->prompt, 'rows' => 3, 'required' => true])

                        <div class="field" data-answers="choice" @if ($type === 'true_false') hidden @endif>
                            <span class="field-label"><span>Answers <small class="muted" data-hint-single>· tick the one correct answer</small><small class="muted" data-hint-multiple hidden>· tick every correct answer</small></span></span>
                            @error('options')<span class="error-text">{{ $message }}</span>@enderror
                            <div class="option-rows" data-option-rows>
                                @foreach ($rows as $row)
                                    <div class="option-row">
                                        <label class="option-correct" title="Correct answer">
                                            <input type="checkbox" name="correct[]" value="{{ $row['key'] }}" @checked($row['correct'])>
                                            <span>✓</span>
                                        </label>
                                        <input type="text" name="options[{{ $row['key'] }}][text]" value="{{ $row['text'] }}" placeholder="Answer {{ chr(65 + $loop->index) }}" aria-label="Answer {{ chr(65 + $loop->index) }}">
                                        <button type="button" class="btn btn-ghost btn-sm btn-icon" data-remove-option title="Remove" aria-label="Remove answer">×</button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" data-add-option>@include('admin.partials.icon', ['name' => 'plus']) Add answer</button>
                        </div>

                        <div class="field" data-answers="true_false" @if ($type !== 'true_false') hidden @endif>
                            <span class="field-label"><span>The statement is…</span></span>
                            @error('tf_answer')<span class="error-text">{{ $message }}</span>@enderror
                            <div class="tf-pick">
                                <label><input type="radio" name="tf_answer" value="true" @checked($tf === 'true')> <span>True</span></label>
                                <label><input type="radio" name="tf_answer" value="false" @checked($tf === 'false')> <span>False</span></label>
                            </div>
                        </div>

                        @include('admin.fields.textarea', ['name' => 'explanation', 'label' => 'Explanation', 'value' => $question->explanation, 'rows' => 3, 'help' => 'Shown after answering (practice) or in the review. Explain why the answer is right.'])
                    </div>
                </section>
            </div>

            <div class="form-side">
                <section class="card">
                    <div class="card-head"><h2>Save</h2></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'points', 'label' => 'Points', 'type' => 'number', 'value' => $question->points ?? 1, 'required' => true, 'attrs' => 'min="1" max="100"'])
                        @include('admin.fields.image', ['name' => 'image', 'remove' => 'remove_image', 'label' => 'Picture (optional)', 'value' => $question->image, 'help' => 'E.g. a screenshot of a worksheet.'])
                    </div>
                    <div class="card-foot save-bar">
                        <button type="submit" class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Save question</button>
                        <button type="submit" class="btn btn-ghost btn-block" name="add_another" value="1">Save &amp; add another</button>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(() => {
    const rows = document.querySelector('[data-option-rows]');
    let next = {{ $rows->max('key') + 1 }};
    const single = () => document.querySelector('[data-qtype]:checked')?.value === 'single';

    const sync = () => {
        const type = document.querySelector('[data-qtype]:checked')?.value;
        document.querySelector('[data-answers="choice"]').hidden = type === 'true_false';
        document.querySelector('[data-answers="true_false"]').hidden = type !== 'true_false';
        document.querySelector('[data-hint-single]').hidden = type !== 'single';
        document.querySelector('[data-hint-multiple]').hidden = type !== 'multiple';
    };
    document.querySelectorAll('[data-qtype]').forEach((r) => r.addEventListener('change', sync));
    sync();

    // One correct answer for "single": ticking one unticks the others.
    rows.addEventListener('change', (e) => {
        if (e.target.name === 'correct[]' && e.target.checked && single()) {
            rows.querySelectorAll('input[name="correct[]"]').forEach((c) => { if (c !== e.target) c.checked = false; });
        }
    });
    rows.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove-option]') && rows.children.length > 2) e.target.closest('.option-row').remove();
    });
    document.querySelector('[data-add-option]').addEventListener('click', () => {
        const row = rows.firstElementChild.cloneNode(true);
        const key = next++;
        row.querySelector('input[type=checkbox]').value = key;
        row.querySelector('input[type=checkbox]').checked = false;
        const text = row.querySelector('input[type=text]');
        text.name = `options[${key}][text]`;
        text.value = '';
        text.placeholder = 'Answer ' + String.fromCharCode(65 + rows.children.length);
        rows.append(row);
        text.focus();
    });
})();
</script>
@endpush
