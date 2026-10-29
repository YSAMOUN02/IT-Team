@extends('admin.layout')

@section('title', 'Settings')
@section('subtitle', 'Brand, contact details and footer used across the whole website.')
@section('crumbs')<span aria-hidden="true">/</span><span>System</span><span aria-hidden="true">/</span><span>Settings</span>@endsection

@section('content')
    <form method="post" enctype="multipart/form-data" data-main-form action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')

        <div class="form-grid reveal">
            <div>
                @foreach ($groups as $id => $group)
                    <section class="card" id="settings-{{ $id }}">
                        <div class="card-head"><h2>{{ $group['label'] }}</h2></div>
                        <div class="card-body fields">
                            @foreach ($group['fields'] as $key => $meta)
                                @if ($meta['type'] === 'socials')
                                    @include('admin.fields.socials', ['label' => $meta['label']])
                                @elseif ($meta['type'] === 'image')
                                    @include('admin.fields.image', ['name' => "uploads[$key]", 'remove' => "remove[$key]", 'label' => $meta['label'], 'value' => $values[$key] ?? null,
                            'formats' => 'JPG, PNG, WebP, GIF, SVG or ICO', 'accept' => \App\Support\Media::accept([...\App\Support\Media::IMAGE_TYPES, 'ico'])])
                                @elseif ($meta['type'] === 'textarea')
                                    @include('admin.fields.textarea', ['name' => "settings[$key]", 'label' => $meta['label'], 'value' => $values[$key] ?? null, 'rows' => 3])
                                @else
                                    @include('admin.fields.input', [
                                        'name' => "settings[$key]", 'label' => $meta['label'], 'type' => $meta['type'] === 'url' ? 'text' : $meta['type'],
                                        'value' => $values[$key] ?? $meta['default'] ?? null,
                                        'attrs' => $meta['type'] === 'number' ? 'min="'.($meta['min'] ?? 0).'" max="'.($meta['max'] ?? 999).'" step="1" style="max-width:140px"' : '',
                                    ])
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <div class="form-side">
                <section class="card">
                    <div class="card-head"><h2>Save</h2></div>
                    <div class="card-body" style="display:grid;gap:4px">
                        @foreach ($groups as $id => $group)
                            <a href="#settings-{{ $id }}" class="mono" style="font-size:12.5px">{{ chr(64 + $loop->iteration) }} · {{ $group['label'] }}</a>
                        @endforeach
                    </div>
                    <div class="card-foot save-bar">
                        <button type="submit" class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Save settings</button>
                        <span class="help" style="text-align:center">Tip: press <kbd>Ctrl</kbd> + <kbd>S</kbd> to save</span>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
