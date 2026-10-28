{{--
    @include('admin.fields.input', ['name' => 'title', 'label' => 'Title', 'value' => $x])
    Optional: type, help, required, placeholder, prefix (shown before the box), max (counter), attrs (raw attributes)
--}}
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($key);
@endphp
<div @class(['field', 'has-error' => $error])>
    <label class="field-label" for="f-{{ $key }}">
        <span>{{ $label }}@if (! empty($required))<em>*</em>@endif</span>
        @isset($max)<span class="counter" data-counter></span>@endisset
    </label>
    @isset($prefix)<div class="affix"><span>{{ $prefix }}</span>@endisset
    <input id="f-{{ $key }}" type="{{ $type ?? 'text' }}" name="{{ $name }}" value="{{ old($key, $value ?? '') }}"
           @if (! empty($required)) required @endif
           @isset($placeholder) placeholder="{{ $placeholder }}" @endisset
           @isset($max) data-max="{{ $max }}" @endisset
           {!! $attrs ?? '' !!}>
    @isset($prefix)</div>@endisset
    @if ($error)
        <span class="error-text">{{ $error }}</span>
    @elseif (! empty($help))
        <span class="help">{{ $help }}</span>
    @endif
</div>
