{{-- Drag-and-drop image picker. $name = file input name, $remove = "remove" checkbox name, $value = stored path,
     optional $formats, $accept (".jpg,.png", default: Media::IMAGE_TYPES) and $maxKb (default: the server's limit).
     Type and size are also checked in the browser when a file is picked (public/js/admin.js). --}}
@php
    $url = media_url($value ?? null);
    $error = $errors->first(str_replace(['[', ']'], ['.', ''], $name));
    $accept ??= \App\Support\Media::accept();
    $maxKb ??= \App\Support\Media::uploadMaxKb();
@endphp
<div @class(['field', 'has-error' => $error])>
    <span class="field-label"><span>{{ $label }}</span></span>
    <label @class(['dropzone', 'has-image' => $url]) data-dropzone>
        <img src="{{ $url }}" alt="" @unless ($url) hidden @endunless>
        <span class="dz-empty">
            @include('admin.partials.icon', ['name' => 'upload'])
            <span class="dz-text"><b>Choose an image</b> or drag it here<br><small>{{ $formats ?? 'JPG, PNG, WebP, GIF or SVG' }} · up to {{ \App\Support\Media::sizeLabel($maxKb) }}</small></span>
        </span>
        <input type="file" name="{{ $name }}" accept="{{ $accept }}" data-max-kb="{{ $maxKb }}">
    </label>
    <div class="dz-meta">
        <span class="dz-name" data-dz-name>{{ $value ? basename($value) : '' }}</span>
        @if ($value)
            <label class="check"><input type="checkbox" name="{{ $remove }}" value="1" data-dz-remove> Remove</label>
        @endif
    </div>
    <span class="error-text" data-dz-error @unless ($error) hidden @endunless>{{ $error }}</span>
    @if (! empty($help))
        <span class="help">{{ $help }}</span>
    @endif
</div>
