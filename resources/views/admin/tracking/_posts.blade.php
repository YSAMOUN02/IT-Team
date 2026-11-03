{{-- Tracking › Posts: tutorials and news. Click a row to see one post on its own. --}}
@php
    $maxViews = max(1, $rows->max('views'));
    $selectedTitle = $selected ? \Illuminate\Support\Str::limit($selected->title, 60) : null;
@endphp
<section class="kpis kpis-mini track-kpis" aria-label="Summary" id="detail">
    @include('admin.tracking._kpi', ['kpiIcon' => 'eye', 'kpiLabel' => 'Views', 'kpiValue' => number_format($totals->views), 'kpiChange' => $change['views'], 'kpiNote' => 'times opened'])
    @include('admin.tracking._kpi', ['kpiIcon' => 'users', 'kpiLabel' => 'Readers', 'kpiValue' => number_format($totals->visitors), 'kpiChange' => $change['visitors'], 'kpiNote' => 'different people'])
    @include('admin.tracking._kpi', ['kpiIcon' => 'clock', 'kpiLabel' => 'Time on a post', 'kpiValue' => $time($totals->avg_seconds), 'kpiNote' => 'on average'])
    @if ($selected)
        @include('admin.tracking._kpi', ['kpiIcon' => 'calendar', 'kpiLabel' => 'Published', 'kpiValue' => $selected->published_at?->format('j M Y') ?? '—', 'kpiNote' => \App\Models\Article::SINGULAR[$selected->type] ?? 'post'])
    @else
        @include('admin.tracking._kpi', ['kpiIcon' => 'tutorials', 'kpiLabel' => 'Posts read', 'kpiValue' => $totals->read.' of '.$totals->all, 'kpiNote' => 'had at least 1 view'])
    @endif
</section>

<div class="charts">
    @include('admin.tracking._chart', ['chartTitle' => 'Views per '.$per, 'chartNote' => $selectedTitle ?? 'All posts'.($postType ? ' · '.$postTypes[$postType] : ''), 'chartData' => $chart, 'chartUnit' => 'views', 'chartTotal' => $totals->views])
    @include('admin.tracking._sources', ['sourcesFor' => $selectedTitle])
</div>

<section class="card track-table">
    <div class="card-head">
        <div>
            <h2>Posts</h2>
            <p>@if ($selected)<a href="{{ $here(['item' => null]) }}">← Show all posts</a>@else Most read first. Click a post to see it on its own.@endif</p>
        </div>
        <nav class="segmented" aria-label="Kind of post">
            <a href="{{ $here(['type' => null, 'item' => null]) }}" @class(['on' => ! $postType])>All</a>
            @foreach ($postTypes as $key => $typeLabel)
                <a href="{{ $here(['type' => $key, 'item' => null]) }}" @class(['on' => $postType === $key])>{{ $typeLabel }}</a>
            @endforeach
        </nav>
    </div>
    @if ($rows->isEmpty())
        <div class="empty-state"><p>No posts yet.</p></div>
    @else
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Post</th><th class="num">Views</th><th class="num hide-sm">Readers</th><th class="num hide-sm">Time on post</th></tr></thead>
                <tbody>
                @foreach ($rows as $row)
                    @php
                        $isSelected = $selected?->id === $row->article->id;
                    @endphp
                    <tr @class(['is-selected' => $isSelected, 'is-quiet' => ! $row->views])>
                        <td>
                            <div class="row-title">
                                @if ($row->article->image)<img class="track-thumb" src="{{ $row->article->mediaUrl() }}" alt="" loading="lazy">@endif
                                <div>
                                    <a href="{{ $here(['item' => $isSelected ? null : $row->article->id]) }}#detail">{{ \Illuminate\Support\Str::limit($row->article->title, 70) }}</a>
                                    <span class="row-sub">
                                        {{ \App\Models\Article::TYPES[$row->article->type] ?? '' }} · {{ $row->article->published_at?->format('j M Y') }}
                                        <span class="show-sm">· {{ $row->visitors }} {{ str('reader')->plural($row->visitors) }}</span>
                                        · <a href="{{ $row->article->url() }}" target="_blank" rel="noopener">Open ↗</a>
                                    </span>
                                    <span class="bar-line"><span style="width: {{ round($row->views * 100 / $maxViews) }}%"></span></span>
                                </div>
                            </div>
                        </td>
                        <td class="num"><b>{{ number_format($row->views) }}</b></td>
                        <td class="num hide-sm">{{ number_format($row->visitors) }}</td>
                        <td class="num hide-sm">{{ $time($row->avg_seconds) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
