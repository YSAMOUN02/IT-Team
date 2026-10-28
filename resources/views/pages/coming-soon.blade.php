@extends('layouts.site')

@section('title', $page->title)
@section('admin_edit', route('admin.pages.edit', $page))

@section('content')
    <section class="section coming-soon">
        <div class="container narrow">
            @if ($page->field('intro.image'))
                <img src="{{ media_url($page->field('intro.image')) }}" alt="">
            @endif
            <h1>{{ $page->field('intro.title', $page->title) }}</h1>
            @if ($page->field('intro.text'))
                <p class="lead">{{ $page->field('intro.text') }}</p>
            @endif
            @if ($page->body)
                <div class="prose">{!! $page->body !!}</div>
            @endif
            <a class="btn btn-primary" href="{{ route('home') }}">{{ __('site.back_home') }}</a>
        </div>
    </section>
@endsection
