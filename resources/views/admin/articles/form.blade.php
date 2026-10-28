@extends('admin.layout')

@php
    $label = \App\Models\Article::TYPES[$article->type];
    $singular = \App\Models\Article::SINGULAR[$article->type];
    $section = \App\Models\Article::section($article->type);
@endphp

@section('title', $article->exists ? $article->title : 'New '.$singular)
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.articles.index', ['type' => $article->type]) }}">{{ $label }}</a>
    <span aria-hidden="true">/</span><span>{{ $article->exists ? 'Edit' : 'New' }}</span>
@endsection
@section('actions')
    @if ($article->exists)
        <form method="post" action="{{ route('admin.articles.destroy', $article) }}" data-confirm="Delete “{{ $article->title }}”? This cannot be undone.">
            @csrf @method('DELETE')
            <button class="btn btn-danger">@include('admin.partials.icon', ['name' => 'trash']) Delete</button>
        </form>
    @endif
@endsection

@section('content')
    <form method="post" enctype="multipart/form-data" data-main-form
          action="{{ $article->exists ? route('admin.articles.update', $article) : route('admin.articles.store') }}">
        @csrf
        @if ($article->exists) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $article->type }}">

        <div class="form-grid reveal">
            <div>
                <section class="card">
                    <div class="card-head"><div><h2>Content</h2><p>What visitors read on the {{ strtolower($label) }} page.</p></div></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'title', 'label' => 'Title', 'value' => $article->title, 'required' => true, 'attrs' => 'data-slug-source'])
                        @include('admin.fields.input', ['name' => 'slug', 'label' => 'Web address', 'value' => $article->slug, 'prefix' => '/'.$section.'/', 'attrs' => 'data-slug', 'help' => 'Leave empty to make it from the title. Khmer letters are fine.'])
                        @include('admin.fields.textarea', ['name' => 'excerpt', 'label' => 'Short summary', 'value' => $article->excerpt, 'rows' => 3, 'max' => 300, 'help' => 'Shown on cards and in search results. Taken from the content when empty.'])
                        @include('admin.fields.textarea', ['name' => 'body', 'label' => 'Body', 'value' => $article->body, 'editor' => true])
                    </div>
                </section>
            </div>

            <div class="form-side">
                @include('admin.fields.publish', ['published' => $article->is_published, 'viewUrl' => $article->exists ? $article->url() : null, 'model' => $article])
                <section class="card">
                    <div class="card-head"><h2>Details</h2></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'published_at', 'label' => 'Publish date', 'type' => 'datetime-local', 'value' => $article->published_at?->format('Y-m-d\TH:i'), 'help' => 'Newest first. A future date schedules it.'])
                        @include('admin.fields.image', ['name' => 'image', 'remove' => 'remove_image', 'label' => 'Featured image', 'value' => $article->image, 'help' => 'Best at 1200 × 675 (16:9).'])
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
