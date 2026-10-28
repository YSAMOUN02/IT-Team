@extends('layouts.site')

@php($section = \App\Models\Article::section($article->type))
@section('title', $article->title)
@section('admin_edit', route('admin.articles.edit', $article))
@section('description', $article->excerptText(160))
@if ($article->image)
    @section('og_image', url($article->mediaUrl()))
@endif
@section('og_type', 'article')

@push('head')
    @if ($article->published_at)
        <meta property="article:published_time" content="{{ $article->published_at->toAtomString() }}">
    @endif
    <meta property="article:modified_time" content="{{ $article->updated_at?->toAtomString() }}">
@endpush

@push('schema')
    {!! \App\Support\Seo::jsonLd(['@context' => 'https://schema.org', '@graph' => [
        \App\Support\Seo::article($article),
        \App\Support\Seo::breadcrumbs([[__('site.'.$section), $article->indexUrl()], [$article->title, $article->url()]]),
    ]]) !!}
@endpush

@section('content')
    <div class="read-progress" data-read-progress aria-hidden="true"><span></span></div>
    <article data-read-target>
        <header class="article-header">
            <div class="container narrow">
                <p class="eyebrow">
                    <a href="{{ $article->indexUrl() }}">{{ __('site.'.$section) }}</a>
                    @if ($article->published_at)
                        <span aria-hidden="true">·</span>
                        <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->format('d/m/Y') }}</time>
                    @endif
                </p>
                <h1>{{ $article->title }}</h1>
                @if ($article->type === 'news' && $article->excerpt)
                    <p class="lead">{{ $article->excerpt }}</p>
                @endif
            </div>
        </header>

        <div class="section section-flush-top">
            <div class="container narrow">
                @if ($article->image)
                    <img class="featured-image" src="{{ $article->mediaUrl() }}" alt="{{ $article->title }}">
                @endif
                @include('partials.ad', ['placement' => 'article_top'])
                <div class="prose entry">{!! $article->body !!}</div>
                @include('partials.ad', ['placement' => 'article_bottom'])
            </div>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="section section-tint">
            <div class="container">
                <div class="section-head">
                    <h2>{{ $article->type === 'tutorial' ? __('site.our_lessons') : __('site.'.$section) }}</h2>
                    <a class="btn btn-outline" href="{{ $article->indexUrl() }}">{{ $article->type === 'tutorial' ? __('site.more_lessons') : __('site.view_all') }}</a>
                </div>
                <div class="grid grid-3">
                    @foreach ($related as $article)
                        @include('partials.article-card')
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
