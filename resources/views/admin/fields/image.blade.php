{{-- Drag-and-drop image picker. $name = file input name, $remove = "remove" checkbox name, $value = stored path, optional $formats. --}}
@php($url = media_url($value ?? null))
<div class="field">
    <span class="field-label"><span>{{ $label }}</span></span>
    <label @class(['dropzone', 'has-image' => $url]) data-dropzone>
        <img src="{{ $url }}" alt="" @unless ($url) hidden @endunless>
        <span class="dz-empty">
            @include('admin.partials.icon', ['name' => 'upload'])
            <span class="dz-text"><b>Choose an image</b> or drag it here<br><small>{{ $formats ?? 'JPG, PNG, WebP or SVG' }} · up to 8 MB</small></span>
        </span>
        <input type="file" name="{{ $name }}" accept="image/*">
    </label>
    <div class="dz-meta">
        <span class="dz-name" data-dz-name>{{ $value ? basename($value) : '' }}</span>
        @if ($value)
            <label class="check"><input type="checkbox" name="{{ $remove }}" value="1" data-dz-remove> Remove</label>
        @endif
    </div>
    @if (! empty($help))
        <span class="help">{{ $help }}</span>
    @endif
</div>
