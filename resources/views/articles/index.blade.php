@extends('layouts.site')

@php($section = \App\Models\Article::section($type))
@section('title', $page?->title ?? __('site.'.$section))
@section('description', $page?->meta_description ?: strip_tags((string) $page?->field('intro.text')))

@section('content')
    @include('partials.page-header', [
        'title' => $page?->field('intro.title') ?? __('site.'.$section),
        'text' => $page?->field('intro.text'),
    ])

    <section class="section">
        <div class="container">
            @include('partials.ad', ['placement' => 'listing'])
            @if ($articles->isEmpty())
                <p class="empty">{{ __('site.no_items') }}</p>
            @else
                <div class="grid grid-3">
                    @foreach ($articles as $article)
                        @include('partials.article-card', ['showExcerpt' => true])
                    @endforeach
                </div>
                {{ $articles->links('partials.pagination') }}
            @endif
        </div>
    </section>
@endsection
