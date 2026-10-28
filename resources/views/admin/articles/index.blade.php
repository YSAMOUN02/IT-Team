@extends('admin.layout')

@php
    $label = \App\Models\Article::TYPES[$type];
    $singular = \App\Models\Article::SINGULAR[$type];
    $icon = ['tutorial' => 'tutorials', 'news' => 'news'][$type];
    $subtitles = [
        'tutorial' => 'Free lessons shown on the Tutorials page and the home page.',
        'news' => 'News, announcements and blog articles, shown on the News page and the home page.',
    ];
@endphp

@section('title', $label)
@section('subtitle', $subtitles[$type])
@section('crumbs')<span aria-hidden="true">/</span><span>Content</span><span aria-hidden="true">/</span><span>{{ $label }}</span>@endsection
@section('actions')
    <a class="btn btn-ghost" href="{{ route(\App\Models\Article::section($type).'.index') }}" target="_blank" rel="noopener">@include('admin.partials.icon', ['name' => 'external']) View page</a>
    <a class="btn btn-primary" href="{{ route('admin.articles.create', ['type' => $type]) }}">@include('admin.partials.icon', ['name' => 'plus']) New {{ $singular }}</a>
@endsection

@section('content')
    <div class="card reveal">
        <form class="toolbar" method="get">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="search">
                @include('admin.partials.icon', ['name' => 'search'])
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search {{ strtolower($label) }}…" aria-label="Search {{ strtolower($label) }}">
            </div>
            <nav class="segmented" aria-label="Filter by status">
                @foreach (['all' => 'All', 'live' => 'Live', 'hidden' => 'Hidden'] as $key => $text)
                    <a href="{{ route('admin.articles.index', array_filter(['type' => $type, 'status' => $key, 'q' => request('q')])) }}" @class(['on' => $status === $key])>{{ $text }} <span class="muted mono">{{ $totals[$key] }}</span></a>
                @endforeach
            </nav>
            <span class="toolbar-count">{{ $articles->total() }} {{ \Illuminate\Support\Str::plural('item', $articles->total()) }}</span>
        </form>

        @if ($articles->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => $icon])</div>
                @if (request('q') || $status !== 'all')
                    <h3>Nothing matches</h3>
                    <p>Try another search or filter.</p>
                    <a class="btn btn-ghost" href="{{ route('admin.articles.index', ['type' => $type]) }}">Clear filters</a>
                @else
                    <h3>No {{ strtolower($label) }} yet</h3>
                    <p>Create the first {{ $singular }} and it will appear on the website.</p>
                    <a class="btn btn-primary" href="{{ route('admin.articles.create', ['type' => $type]) }}">@include('admin.partials.icon', ['name' => 'plus']) New {{ $singular }}</a>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Title</th><th>Status</th><th>Date</th><th>Updated</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($articles as $article)
                        @php($scheduled = $article->is_published && $article->published_at?->isFuture())
                        <tr>
                            <td>
                                <div class="row-title">
                                    @if ($article->image)
                                        <img class="thumb" src="{{ $article->mediaUrl() }}" alt="">
                                    @else
                                        <span class="thumb">@include('admin.partials.icon', ['name' => 'image'])</span>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.articles.edit', $article) }}">{{ $article->title }}</a>
                                        <span class="row-sub">{{ $article->excerptText(90) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($scheduled)
                                    <span class="status status-sched">Scheduled</span>
                                @else
                                    <span class="status {{ $article->is_published ? 'status-on' : 'status-off' }}">{{ $article->is_published ? 'Live' : 'Hidden' }}</span>
                                @endif
                            </td>
                            <td class="num">{{ $article->published_at?->format('d M Y') }}</td>
                            <td class="num">{{ $article->updated_at?->diffForHumans(short: true) }}</td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm btn-icon" href="{{ $article->url() }}" target="_blank" rel="noopener" title="View on website" aria-label="View on website">@include('admin.partials.icon', ['name' => 'eye'])</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.articles.edit', $article) }}">@include('admin.partials.icon', ['name' => 'edit']) Edit</a>
                                <form method="post" action="{{ route('admin.articles.destroy', $article) }}" data-confirm="Delete “{{ $article->title }}”? This cannot be undone.">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm btn-icon" title="Delete" aria-label="Delete">@include('admin.partials.icon', ['name' => 'trash'])</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $articles->links('admin.pager') }}
        @endif
    </div>
@endsection
