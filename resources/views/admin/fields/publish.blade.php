{{-- Side card with the visibility switch, optional order, the save button and record info. --}}
<div class="card">
    <div class="card-head"><h2>Publish</h2></div>
    <div class="card-body fields">
        <label class="switch">
            <span class="switch-text">
                <strong>Visible on website</strong>
                <span>Turn off to keep it as a hidden draft.</span>
            </span>
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $published ?? true))>
            <span class="switch-ui" aria-hidden="true"></span>
        </label>
        @isset($sort)
            @include('admin.fields.input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'value' => $sort, 'help' => 'Lower numbers are shown first.'])
        @endisset
        {{ $slot ?? '' }}
    </div>
    <div class="card-foot save-bar">
        <button type="submit" class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) {{ $saveLabel ?? 'Save changes' }}</button>
        @if (! empty($viewUrl))
            <a class="btn btn-ghost btn-block" href="{{ $viewUrl }}" target="_blank" rel="noopener">@include('admin.partials.icon', ['name' => 'external']) View on website</a>
        @endif
        <span class="help" style="text-align:center">Tip: press <kbd>Ctrl</kbd> + <kbd>S</kbd> to save</span>
    </div>
    @if (! empty($model) && $model->exists)
        <div class="card-body" style="border-top:1px solid var(--line-2)">
            <dl class="meta-list">
                <dt>Created</dt><dd>{{ $model->created_at?->format('d M Y, H:i') }}</dd>
                <dt>Updated</dt><dd>{{ $model->updated_at?->diffForHumans() }}</dd>
                @if ($model->wp_id ?? null)
                    <dt>Imported</dt><dd>WordPress #{{ $model->wp_id }}</dd>
                @endif
            </dl>
        </div>
    @endif
</div>
