{{-- Optional: rows, help, required, max (counter), editor (rich text) --}}
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($key);
@endphp
<div @class(['field', 'has-error' => $error])>
    <label class="field-label" for="f-{{ $key }}">
        <span>{{ $label }}@if (! empty($required))<em>*</em>@endif</span>
        @isset($max)<span class="counter" data-counter></span>@endisset
    </label>
    <textarea id="f-{{ $key }}" name="{{ $name }}" rows="{{ $rows ?? 4 }}"
              @if (! empty($required) && empty($editor)) required @endif
              @isset($max) data-max="{{ $max }}" @endisset
              @if (! empty($editor)) data-editor @endif>{{ old($key, $value ?? '') }}</textarea>
    @if ($error)
        <span class="error-text">{{ $error }}</span>
    @elseif (! empty($help))
        <span class="help">{{ $help }}</span>
    @endif
    @if (! empty($editor))
        <p class="editor-help">
            Paste screenshots straight in (<kbd>Win</kbd>+<kbd>Shift</kbd>+<kbd>S</kbd>, then <kbd>Ctrl</kbd>+<kbd>V</kbd>) ·
            <b>Insert block</b> adds tips, steps, exercises and hidden answers · <b>Word</b> imports a .docx with its pictures ·
            type <kbd>#</kbd> <kbd>##</kbd> + space for headings, <kbd>1.</kbd> or <kbd>-</kbd> + space for lists · <b>File → Restore last draft</b> brings back unsaved text.
        </p>
    @endif
</div>
@if (! empty($editor))
    @include('admin.fields.editor-script')
@endif
