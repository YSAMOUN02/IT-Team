@extends('admin.layout')

@php
    $type = old('type', $question->type);
    $blankRow = fn ($i) => ['key' => $i, 'text' => '', 'match' => '', 'slot' => '', 'correct' => false];
    $rows = old('options') ? collect(old('options'))->map(fn ($o, $k) => ['key' => $k, 'text' => $o['text'] ?? '', 'match' => $o['match'] ?? '', 'slot' => $o['slot'] ?? '', 'correct' => in_array((string) $k, array_map('strval', (array) old('correct', [])), true)])->values()
        // Saved answers are keyed by their id, so saving updates them in place (students' past answers point at these ids).
        : ($options->isNotEmpty() ? $options->map(fn ($o) => ['key' => $o->id, 'text' => $o->text, 'match' => $o->match_text, 'slot' => $o->slot, 'correct' => $o->is_correct])->values()
        : collect(range(0, 3))->map($blankRow));
    $tf = old('tf_answer', $question->exists && $type === 'true_false' ? ($options->first()?->is_correct ? 'true' : 'false') : null);
    // What each type calls its rows, and the hint above them.
    $kinds = [
        'single' => ['Answers', 'tick the one correct answer', 'Answer'],
        'multiple' => ['Answers', 'tick every correct answer', 'Answer'],
        'matching' => ['Pairs', 'students match each left item with its right item, shuffled', 'Left item'],
        'ordering' => ['Items, in the right order', 'students see them shuffled and put them back in this order', 'Step'],
        'dragdrop' => ['Words', 'give each word the number of its blank; a word with no number is an extra that fits nowhere', 'Word'],
    ];
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
                            <div class="segmented qtype-pick" role="radiogroup">
                                @foreach (\App\Models\QuizQuestion::TYPES as $value => $label)
                                    <label class="seg"><input type="radio" name="type" value="{{ $value }}" @checked($type === $value) data-qtype> <span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                        </div>
                        @include('admin.fields.textarea', ['name' => 'prompt', 'label' => 'Question', 'value' => $question->prompt, 'rows' => 3, 'required' => true])
                        <p class="help qtype-note" data-for="dragdrop">Write <code>___</code> (three underscores) where each blank goes, e.g. <code>Use ___ to add up a range, and ___ to count it.</code> The blanks are numbered 1, 2, 3… from the left.</p>

                        <div class="alert alert-info qtype-note" data-for="short long">
                            @include('admin.partials.icon', ['name' => 'edit'])
                            <div>Students type an answer of up to <b data-for="short">255</b><b data-for="long">2,000</b> characters. There is no answer key: in <b>Exam results</b> you give each answer a mark from <b>0% to 100%</b> (steps of 10%), and its points follow (70% of 2 points = 1.4). Put a model answer in <b>Explanation</b> to help whoever marks.</div>
                        </div>

                        <div class="field" data-answers="list" @if (in_array($type, ['true_false', 'short', 'long'], true)) hidden @endif>
                            <span class="field-label"><span>
                                @foreach ($kinds as $kind => [$title, $hint])
                                    <span data-for="{{ $kind }}">{{ $title }} <small class="muted">· {{ $hint }}</small></span>
                                @endforeach
                            </span></span>
                            @error('options')<span class="error-text">{{ $message }}</span>@enderror
                            <div class="option-rows" data-option-rows>
                                @foreach ($rows as $row)
                                    <div class="option-row">
                                        <label class="option-correct" title="Correct answer" data-for="single multiple">
                                            <input type="checkbox" name="correct[]" value="{{ $row['key'] }}" @checked($row['correct'])>
                                            <span>✓</span>
                                        </label>
                                        <span class="option-move" data-for="ordering">
                                            <button type="button" class="btn btn-ghost btn-sm btn-icon" data-move="-1" title="Move up" aria-label="Move up">↑</button>
                                            <button type="button" class="btn btn-ghost btn-sm btn-icon" data-move="1" title="Move down" aria-label="Move down">↓</button>
                                        </span>
                                        <input type="text" name="options[{{ $row['key'] }}][text]" value="{{ $row['text'] }}" data-text aria-label="Answer {{ $loop->iteration }}">
                                        <span class="option-arrow" data-for="matching" aria-hidden="true">→</span>
                                        <input type="text" name="options[{{ $row['key'] }}][match]" value="{{ $row['match'] }}" placeholder="Its match on the right" aria-label="Match {{ $loop->iteration }}" data-for="matching">
                                        <select name="options[{{ $row['key'] }}][slot]" aria-label="Blank" data-for="dragdrop" class="option-slot">
                                            <option value="">Extra word</option>
                                            @for ($n = 1; $n <= 12; $n++)
                                                <option value="{{ $n }}" @selected((string) $row['slot'] === (string) $n)>Blank {{ $n }}</option>
                                            @endfor
                                        </select>
                                        <button type="button" class="btn btn-ghost btn-sm btn-icon" data-remove-option title="Remove" aria-label="Remove">×</button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" data-add-option>@include('admin.partials.icon', ['name' => 'plus']) Add row</button>
                        </div>

                        <div class="field" data-answers="true_false" @if ($type !== 'true_false') hidden @endif>
                            <span class="field-label"><span>The statement is…</span></span>
                            @error('tf_answer')<span class="error-text">{{ $message }}</span>@enderror
                            <div class="tf-pick">
                                <label><input type="radio" name="tf_answer" value="true" @checked($tf === 'true')> <span>True</span></label>
                                <label><input type="radio" name="tf_answer" value="false" @checked($tf === 'false')> <span>False</span></label>
                            </div>
                        </div>

                        @include('admin.fields.textarea', ['name' => 'explanation', 'label' => 'Explanation', 'value' => $question->explanation, 'rows' => 3, 'help' => 'Shown after answering (practice) or in the review. Explain why the answer is right. For a short answer: the model answer, shown to the marker too.'])
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
    const placeholders = @json(collect($kinds)->map(fn ($k) => $k[2]));
    let next = {{ (int) $rows->map(fn ($r) => is_numeric($r['key']) ? (int) $r['key'] : 0)->max() + 1 }};
    const type = () => document.querySelector('[data-qtype]:checked')?.value;

    // Show the fields this type uses; number the placeholders ("Step 1", "Word 2"…).
    const sync = () => {
        const t = type();
        document.querySelector('[data-answers="list"]').hidden = ['true_false', 'short', 'long'].includes(t);
        document.querySelector('[data-answers="true_false"]').hidden = t !== 'true_false';
        document.querySelectorAll('[data-for]').forEach((el) => { el.hidden = !el.dataset.for.split(' ').includes(t); });
        rows.querySelectorAll('[data-text]').forEach((input, i) => {
            input.placeholder = (placeholders[t] || 'Answer') + ' ' + (['single', 'multiple'].includes(t) ? String.fromCharCode(65 + i) : i + 1);
        });
    };
    document.querySelectorAll('[data-qtype]').forEach((r) => r.addEventListener('change', sync));
    sync();

    // One correct answer for "single": ticking one unticks the others.
    rows.addEventListener('change', (e) => {
        if (e.target.name === 'correct[]' && e.target.checked && type() === 'single') {
            rows.querySelectorAll('input[name="correct[]"]').forEach((c) => { if (c !== e.target) c.checked = false; });
        }
    });
    rows.addEventListener('click', (e) => {
        const row = e.target.closest('.option-row');
        if (e.target.closest('[data-remove-option]') && rows.children.length > 1) { row.remove(); sync(); }
        const move = e.target.closest('[data-move]');
        if (move) {
            const sibling = move.dataset.move === '-1' ? row.previousElementSibling : row.nextElementSibling;
            if (sibling) { move.dataset.move === '-1' ? sibling.before(row) : sibling.after(row); sync(); move.focus(); }
        }
    });
    document.querySelector('[data-add-option]').addEventListener('click', () => {
        const row = rows.firstElementChild.cloneNode(true);
        const key = next++;
        row.querySelector('input[type=checkbox]').value = key;
        row.querySelector('input[type=checkbox]').checked = false;
        row.querySelectorAll('[name^="options["]').forEach((field) => {
            field.name = field.name.replace(/options\[[^\]]+\]/, `options[${key}]`);
            field.value = '';
        });
        rows.append(row);
        sync();
        row.querySelector('[data-text]').focus();
    });
})();
</script>
@endpush
