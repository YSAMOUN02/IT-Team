@extends('layouts.site')

@section('title', $page->title)
@section('admin_edit', route('admin.pages.edit', $page))
@section('description', (string) $page->meta_description)

@section('content')
    @include('partials.page-header', ['title' => $page->field('intro.title', $page->title), 'text' => $page->field('intro.text')])

    @if ($page->body)
        <section class="section">
            <div class="container narrow">
                <div class="prose entry">{!! $page->body !!}</div>
            </div>
        </section>
    @endif
@endsection
