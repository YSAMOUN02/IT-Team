@extends('admin.layout')

@php
    $url = $page->slug === 'home' ? url('/') : url($page->slug);
@endphp

@section('title', $page->title)
@section('subtitle', $definition['label'].' · '.count($definition['groups']).' sections')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.pages.index') }}">Pages</a>
    <span aria-hidden="true">/</span><span>Edit</span>
@endsection

@section('content')
    <form method="post" enctype="multipart/form-data" data-main-form action="{{ route('admin.pages.update', $page) }}">
        @csrf @method('PUT')

        <div class="form-grid reveal">
            <div>
                @foreach ($definition['groups'] as $group => $config)
                    <section class="card" id="section-{{ $group }}">
                        <div class="card-head">
                            <div><h2><span class="muted mono">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>&nbsp; {{ $config['label'] }}</h2></div>
                        </div>
                        <div class="card-body fields">
                            @foreach ($config['fields'] as $field => $meta)
                                @php
                                    $value = $page->field("$group.$field");
                                    $name = "sections[$group][$field]";
                                @endphp
                                @switch($meta['type'])
                                    @case('image')
                                        @include('admin.fields.image', ['name' => "uploads[$group][$field]", 'remove' => "remove[$group][$field]", 'label' => $meta['label'], 'value' => $value])
                                        @break
                                    @case('list')
                                        @include('admin.fields.textarea', ['name' => $name, 'label' => $meta['label'], 'value' => implode("\n", (array) $value), 'rows' => 6, 'help' => 'One item per line.'])
                                        @break
                                    @case('textarea')
                                        @include('admin.fields.textarea', ['name' => $name, 'label' => $meta['label'], 'value' => $value, 'rows' => 3])
                                        @break
                                    @case('html')
                                        @include('admin.fields.textarea', ['name' => $name, 'label' => $meta['label'], 'value' => $value, 'rows' => 7, 'help' => 'Basic HTML is allowed: <p>, <strong>, <br>, <a>.'])
                                        @break
                                    @default
                                        @include('admin.fields.input', ['name' => $name, 'label' => $meta['label'], 'value' => $value, 'help' => $meta['type'] === 'url' ? 'A page on this site, e.g. /contact, or a full https:// link.' : null])
                                @endswitch
                            @endforeach
                        </div>
                    </section>
                @endforeach

                @if ($definition['body'] ?? false)
                    <section class="card">
                        <div class="card-head"><h2>Page content</h2></div>
                        <div class="card-body">
                            @include('admin.fields.textarea', ['name' => 'body', 'label' => 'Content', 'value' => $page->body, 'editor' => true])
                        </div>
                    </section>
                @endif
            </div>

            <div class="form-side">
                @include('admin.fields.publish', ['published' => $page->is_published, 'viewUrl' => $url, 'model' => $page])
                <section class="card">
                    <div class="card-head"><div><h2>Search engines</h2><p>How the page appears on Google.</p></div></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'title', 'label' => 'Page title', 'value' => $page->title, 'required' => true, 'max' => 60])
                        @include('admin.fields.textarea', ['name' => 'meta_description', 'label' => 'Description', 'value' => $page->meta_description, 'rows' => 3, 'max' => 160])
                    </div>
                </section>
                @if (count($definition['groups']) > 2)
                    <section class="card">
                        <div class="card-head"><h2>Jump to section</h2></div>
                        <div class="card-body" style="display:grid;gap:4px">
                            @foreach ($definition['groups'] as $group => $config)
                                <a href="#section-{{ $group }}" class="mono" style="font-size:12.5px">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }} · {{ $config['label'] }}</a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </form>
@endsection
