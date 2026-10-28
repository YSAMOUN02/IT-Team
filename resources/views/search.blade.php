@extends('layouts.site')

@section('title', __('site.search'))

@section('content')
    <section class="page-header">
        <div class="container">
            <h1>{{ $term !== '' ? __('site.search_results', ['term' => $term]) : __('site.search') }}</h1>
            <form class="search-inline" action="{{ route('search') }}" method="get" role="search">
                <input type="search" name="s" value="{{ $term }}" placeholder="{{ __('site.search_placeholder') }}" aria-label="{{ __('site.search') }}">
                <button class="btn btn-primary" type="submit">{{ __('site.search') }}</button>
            </form>
        </div>
    </section>

    <section class="section">
        <div class="container">
            @if ($term !== '' && $courses->isEmpty() && $articles->isEmpty())
                <p class="empty">{{ __('site.search_empty') }}</p>
            @endif

            @if ($courses->isNotEmpty())
                <h2 class="results-heading">{{ __('site.courses') }}</h2>
                <div class="grid grid-3">
                    @foreach ($courses as $course)
                        @include('partials.course-card')
                    @endforeach
                </div>
            @endif

            @foreach ($articles->groupBy('type') as $type => $group)
                <h2 class="results-heading">{{ __('site.'.\App\Models\Article::section($type)) }}</h2>
                <div class="grid grid-3">
                    @foreach ($group as $article)
                        @include('partials.article-card', ['showExcerpt' => true])
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
@endsection
