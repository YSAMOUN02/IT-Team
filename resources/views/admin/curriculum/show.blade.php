@extends('admin.layout')

@section('title', $course->title)
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.courses.index') }}">Courses</a>
    <span aria-hidden="true">/</span><span>Curriculum</span>
@endsection
@section('hide_head', true)

@php
    $addButtons = fn ($moduleId) => [
        ['Lesson', route('admin.courses.lessons.create', [$course, 'module' => $moduleId])],
        ['Practice', route('admin.courses.quizzes.create', array_filter([$course, 'type' => 'practice', 'module' => $moduleId]))],
        ['Quiz', route('admin.courses.quizzes.create', array_filter([$course, 'type' => 'quiz', 'module' => $moduleId]))],
        ['Mock exam', route('admin.courses.quizzes.create', array_filter([$course, 'type' => 'mock', 'module' => $moduleId]))],
        ['Final exam', route('admin.courses.quizzes.create', array_filter([$course, 'type' => 'final', 'module' => $moduleId]))],
    ];
    $hasLoose = $outline->contains(fn ($s) => $s['module'] === null);
    $moduleNumber = 0;
@endphp

@section('content')
<div class="reveal">
    @include('admin.courses._workspace', ['course' => $course, 'tab' => 'curriculum'])

    <div class="builder-help">
        <span>@include('admin.partials.icon', ['name' => 'grid']) Drag <b>⠿</b> to reorder modules, lessons and tests, also between modules. Changes save by themselves.</span>
        <span class="muted">{{ $stats['modules'] }} modules · {{ $stats['lessons'] }} lessons · {{ $stats['quizzes'] }} tests</span>
    </div>

    @if ($outline->isEmpty())
        <div class="card">
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'grid'])</div>
                <h3>Start building the course</h3>
                <p>Add the first module below, e.g. “Module 1: Getting started”. Then add lessons, practice, quizzes and a final exam.</p>
            </div>
        </div>
    @endif

    <div data-modules data-reorder-url="{{ route('admin.courses.curriculum.reorder', $course) }}">
        @foreach ($outline as $section)
            @php
                $module = $section['module'];
                if ($module) { $moduleNumber++; }
                $listKey = $module?->id ?? 'none';
            @endphp
            <section class="card module-card" @if ($module) data-module-id="{{ $module->id }}" @else data-loose @endif>
                <div class="card-head module-head">
                    <div class="module-title">
                        @if ($module)
                            <span class="grip module-grip" title="Drag to move the module" aria-hidden="true">⠿</span>
                        @endif
                        <span class="module-num">{{ $module ? str_pad($moduleNumber, 2, '0', STR_PAD_LEFT) : '··' }}</span>
                        @if ($module)
                            <form method="post" action="{{ route('admin.modules.update', $module) }}" class="module-rename">
                                @csrf @method('PUT')
                                <input type="text" name="title" value="{{ $module->title }}" aria-label="Module title" required>
                                <input type="text" name="description" value="{{ $module->description }}" placeholder="Short description (optional)" aria-label="Module description">
                                <button class="btn btn-ghost btn-sm" title="Save module">Save</button>
                            </form>
                        @else
                            <div><h2>Not in a module</h2><p>Drag these into a module above.</p></div>
                        @endif
                    </div>
                    @if ($module)
                        <div class="row-tools">
                            @include('admin.curriculum._move', ['type' => 'module', 'id' => $module->id])
                            <form method="post" action="{{ route('admin.modules.destroy', $module) }}" data-confirm="Delete the module “{{ $module->title }}”? Its lessons and tests are kept and moved to “Not in a module”.">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm btn-icon" title="Delete module" aria-label="Delete module">@include('admin.partials.icon', ['name' => 'trash'])</button>
                            </form>
                        </div>
                    @endif
                </div>

                <ol class="builder-items" data-items="{{ $listKey }}">
                    @foreach ($section['items'] as $item)
                        @php
                            $isQuiz = $item instanceof \App\Models\Quiz;
                            $type = $isQuiz ? 'quiz' : 'lesson';
                            $editUrl = $isQuiz ? route('admin.quizzes.edit', $item) : route('admin.lessons.edit', $item);
                            $viewUrl = $isQuiz ? route('learn.quiz', [$course->slug, $item->slug]) : route('learn.lesson', [$course->slug, $item->slug]);
                        @endphp
                        <li @class(['builder-item', 'is-draft' => ! $item->is_published]) data-key="{{ $type }}:{{ $item->id }}">
                            <span class="grip" title="Drag to move" aria-hidden="true">⠿</span>
                            <span class="builder-icon {{ $isQuiz ? 'q-'.$item->type : '' }}">@include('admin.partials.icon', ['name' => $isQuiz ? 'pages' : 'tutorials'])</span>
                            <div class="builder-main">
                                <a href="{{ $editUrl }}">{{ $item->title }}</a>
                                <span class="builder-meta">
                                    @if ($isQuiz)
                                        <b class="qtype qtype-{{ $item->type }}">{{ \App\Models\Quiz::TYPES[$item->type] }}</b>
                                        {{ $item->questions_count }} questions
                                        @if ($item->time_limit) · {{ $item->time_limit }} min @endif
                                        · pass {{ $item->pass_percent }}%
                                        @if ($item->questions_count === 0)<b class="warn">No questions yet</b>@endif
                                    @else
                                        Lesson @if ($item->duration) · {{ $item->duration }} @endif
                                        @if ($item->hasVideo()) · video @endif
                                        @if ($item->attachment_path) · practice file @endif
                                        @if ($item->solution_path) · solution @endif
                                        @if (! $item->hasVideo() && blank(strip_tags((string) $item->body)))<b class="warn">Empty</b>@endif
                                    @endif
                                    @if ($item->is_preview)<b class="prev">Free preview</b>@endif
                                    @unless ($item->is_published)<b class="draft">Hidden</b>@endunless
                                </span>
                            </div>
                            <div class="row-tools">
                                <form method="post" action="{{ route('admin.curriculum.toggle') }}">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="id" value="{{ $item->id }}">
                                    <button @class(['btn', 'btn-sm', 'btn-icon', 'btn-ghost', 'is-off' => ! $item->is_published]) title="{{ $item->is_published ? 'Visible - click to hide' : 'Hidden - click to show' }}" aria-label="{{ $item->is_published ? 'Hide' : 'Show' }}">
                                        @include('admin.partials.icon', ['name' => $item->is_published ? 'eye' : 'eye-off'])
                                    </button>
                                </form>
                                <a class="btn btn-ghost btn-sm btn-icon" href="{{ $viewUrl }}" target="_blank" rel="noopener" title="View as student" aria-label="View as student">@include('admin.partials.icon', ['name' => 'user'])</a>
                                @include('admin.curriculum._move', ['type' => $type, 'id' => $item->id])
                                <a class="btn btn-ghost btn-sm" href="{{ $editUrl }}">@include('admin.partials.icon', ['name' => 'edit']) Edit</a>
                                <form method="post" action="{{ $isQuiz ? route('admin.quizzes.destroy', $item) : route('admin.lessons.destroy', $item) }}" data-confirm="Delete “{{ $item->title }}”{{ $isQuiz ? ' with all its questions and results' : '' }}?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm btn-icon" title="Delete" aria-label="Delete">@include('admin.partials.icon', ['name' => 'trash'])</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if ($module)
                    <form class="quick-add" method="post" action="{{ route('admin.courses.curriculum.quick-add', $course) }}">
                        @csrf
                        <input type="hidden" name="module" value="{{ $module->id }}">
                        <textarea name="titles" rows="1" required placeholder="Quick add: type a lesson title (paste several lines to add many)" aria-label="New lesson titles" data-autogrow></textarea>
                        <button class="btn btn-ghost btn-sm">@include('admin.partials.icon', ['name' => 'plus']) Add</button>
                    </form>
                @endif

                <div class="card-foot add-row">
                    <span class="muted">Add:</span>
                    @foreach ($addButtons($module?->id) as [$label, $url])
                        <a class="btn btn-ghost btn-sm" href="{{ $url }}">@include('admin.partials.icon', ['name' => 'plus']) {{ $label }}</a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <section class="card">
        <div class="card-head"><div><h2>Add a module</h2><p>A chapter of the course, e.g. “Module {{ $moduleNumber + 1 }}: …”.</p></div></div>
        <form class="card-body module-new" method="post" action="{{ route('admin.modules.store', $course) }}">
            @csrf
            <div class="cols-2">
                @include('admin.fields.input', ['name' => 'title', 'label' => 'Module title', 'value' => '', 'required' => true, 'placeholder' => 'Module '.($moduleNumber + 1).': …'])
                @include('admin.fields.input', ['name' => 'description', 'label' => 'Short description', 'value' => ''])
            </div>
            <button class="btn btn-primary">@include('admin.partials.icon', ['name' => 'plus']) Add module</button>
        </form>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(() => {
    const root = document.querySelector('[data-modules]');
    if (!root || !window.Sortable) return;
    const token = document.querySelector('meta[name=csrf-token]').content;

    const toast = (text, ok = true) => {
        const el = document.createElement('div');
        el.className = 'toast';
        el.innerHTML = '<span class="ok"></span><span></span>';
        el.querySelector('.ok').textContent = ok ? '✓' : '!';
        if (!ok) el.querySelector('.ok').style.background = '#c0392b';
        el.lastChild.textContent = text;
        document.querySelector('.toasts').append(el);
        setTimeout(() => { el.classList.add('leaving'); setTimeout(() => el.remove(), 260); }, 2200);
    };

    const save = async () => {
        const modules = Array.from(root.querySelectorAll('[data-module-id]')).map((m) => Number(m.dataset.moduleId));
        const items = {};
        root.querySelectorAll('[data-items]').forEach((list) => {
            items[list.dataset.items] = Array.from(list.querySelectorAll(':scope > [data-key]')).map((li) => li.dataset.key);
        });
        // Renumber the module badges on screen.
        root.querySelectorAll('[data-module-id] .module-num').forEach((n, i) => { n.textContent = String(i + 1).padStart(2, '0'); });
        try {
            const res = await fetch(root.dataset.reorderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ modules, items }),
            });
            toast(res.ok ? 'Order saved' : 'Could not save the order - reload the page', res.ok);
        } catch (e) {
            toast('Could not save the order - check the connection', false);
        }
    };

    new Sortable(root, { handle: '.module-grip', draggable: '[data-module-id]', animation: 160, ghostClass: 'drag-ghost', onEnd: save });
    root.querySelectorAll('[data-items]').forEach((list) => new Sortable(list, {
        group: 'curriculum', handle: '.grip', animation: 160, ghostClass: 'drag-ghost', onEnd: save,
    }));

    // Quick-add box grows with pasted lines; Enter adds, Shift+Enter makes a new line.
    document.querySelectorAll('[data-autogrow]').forEach((t) => {
        const grow = () => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; };
        t.addEventListener('input', grow);
        t.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); t.form.requestSubmit(); } });
    });
})();
</script>
@endpush
