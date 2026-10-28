@extends('admin.layout')

@section('title', $term !== '' ? 'Results for “'.$term.'”' : 'Search')
@section('crumbs')<span aria-hidden="true">/</span><span>Search</span>@endsection
@section('subtitle', $term !== '' ? array_sum(array_map('count', $groups)).' matches across your content' : 'Type in the search box above to find content.')

@section('content')
    @if ($term !== '' && ! $groups)
        <div class="card">
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'search'])</div>
                <h3>No matches</h3>
                <p>Nothing matches “{{ $term }}”. Try a shorter word, or the Khmer spelling.</p>
            </div>
        </div>
    @endif

    <div class="reveal">
        @foreach ($groups as $label => $items)
            <section class="card results-group">
                <div class="card-head"><h2>{{ $label }} <span class="muted mono">({{ $items->count() }})</span></h2></div>
                <ul class="feed">
                    @foreach ($items as [$title, $url, $image, $meta])
                        <li>
                            @if ($image)
                                <img class="feed-thumb" src="{{ $image }}" alt="">
                            @else
                                <span class="feed-thumb">@include('admin.partials.icon', ['name' => 'pages'])</span>
                            @endif
                            <div class="feed-main">
                                <a class="feed-title" href="{{ $url }}">{{ $title }}</a>
                                @if ($meta)<span class="feed-meta">{{ \Illuminate\Support\Str::limit($meta, 110) }}</span>@endif
                            </div>
                            <a class="btn btn-ghost btn-sm" href="{{ $url }}">Open</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
