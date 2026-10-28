@extends('admin.layout')

@php
    $time = fn ($s) => $s ? ($s >= 60 ? intdiv($s, 60).'m '.str_pad($s % 60, 2, '0', STR_PAD_LEFT).'s' : $s.'s') : '—';
    $kindLabels = ['course' => 'Course page', 'checkout' => 'Clicked Buy', 'lesson' => 'Lesson', 'quiz' => 'Test', 'learn' => 'Course home', 'page' => null];
    $maxCourse = max(1, $courses->max('visitors'));
    $maxPage = max(1, $pages->max('views'));
@endphp

@section('title', 'Visitors')
@section('subtitle', 'Who visits the website, which pages and courses they open, when, and for how long.')
@section('actions')
    <nav class="segmented" aria-label="Period">
        @foreach ($ranges as $key => $label)
            <a href="{{ route('admin.analytics', ['range' => $key]) }}" @class(['on' => $range === $key])>{{ $label }}</a>
        @endforeach
    </nav>
@endsection

@section('content')
<div class="reveal">
    <section class="kpis kpis-5" aria-label="Summary">
        <div class="card kpi">
            <span class="kpi-icon">@include('admin.partials.icon', ['name' => 'users'])</span>
            <div class="kpi-label">Visitors</div>
            <div class="kpi-value">{{ number_format($totals->visitors) }}</div>
            <div class="kpi-note">@include('admin.analytics._change', ['value' => $change['visitors']]) different people</div>
        </div>
        <div class="card kpi">
            <span class="kpi-icon">@include('admin.partials.icon', ['name' => 'calendar'])</span>
            <div class="kpi-label">Visits</div>
            <div class="kpi-value">{{ number_format($totals->visits) }}</div>
            <div class="kpi-note">{{ $totals->pages_per_visit }} pages per visit</div>
        </div>
        <div class="card kpi">
            <span class="kpi-icon">@include('admin.partials.icon', ['name' => 'eye'])</span>
            <div class="kpi-label">Page views</div>
            <div class="kpi-value">{{ number_format($totals->views) }}</div>
            <div class="kpi-note">@include('admin.analytics._change', ['value' => $change['views']]) pages opened</div>
        </div>
        <div class="card kpi">
            <span class="kpi-icon">@include('admin.partials.icon', ['name' => 'clock'])</span>
            <div class="kpi-label">Time on a page</div>
            <div class="kpi-value">{{ $time($totals->avg_seconds) }}</div>
            <div class="kpi-note">on average</div>
        </div>
        <div class="card kpi kpi-live">
            <span class="kpi-icon"><span class="live-dot" aria-hidden="true"></span></span>
            <div class="kpi-label">On the site now</div>
            <div class="kpi-value">{{ number_format($online) }}</div>
            <div class="kpi-note">active in the last 5 minutes</div>
        </div>
    </section>

    @if ($totals->views === 0 && ! $visitor)
        <section class="card">
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'eye'])</div>
                <h3>No visits in this period yet</h3>
                <p>Counting started when this page was added. Open the website on your phone (not signed in as admin) and it shows up here within seconds.</p>
            </div>
        </section>
    @else
        <div class="charts">
            <section class="card">
                <div class="card-head"><div><h2>{{ $range === 'today' ? 'Visitors per hour' : 'Visitors per day' }}</h2><p>{{ $ranges[$range] }}</p></div></div>
                <div class="card-body">
                    <div class="chart-total"><b>{{ number_format($totals->visitors) }}</b><span>visitors</span></div>
                    @include('admin.partials.column-chart', ['data' => $trend, 'unit' => 'visitors', 'title' => 'Visitors '.($range === 'today' ? 'per hour, today' : 'per day, '.strtolower($ranges[$range])), 'periodLabel' => $range === 'today' ? 'Hour' : 'Day'])
                </div>
            </section>
            <section class="card">
                <div class="card-head"><div><h2>Busiest hours</h2><p>Pages opened by hour of the day (Cambodia time)</p></div></div>
                <div class="card-body">
                    @php($peak = collect($hours)->sortByDesc('value')->first())
                    <div class="chart-total"><b>{{ $peak && $peak['value'] ? $peak['label'] : '—' }}</b><span>busiest hour</span></div>
                    @include('admin.partials.column-chart', ['data' => $hours, 'unit' => 'page views', 'title' => 'Page views by hour of the day', 'periodLabel' => 'Hour'])
                </div>
            </section>
        </div>

        <section class="card">
            <div class="card-head"><div><h2>Courses: what people click on</h2><p>Opened the course page → clicked Buy / Enrol → paid → studying. People are counted once per course.</p></div></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Course</th><th class="num">Opened</th><th class="num">Clicked Buy</th><th class="num">Paid</th><th class="num">Studying</th><th class="num">Time on page</th></tr></thead>
                    <tbody>
                    @foreach ($courses as $row)
                        <tr>
                            <td>
                                <a href="{{ $row->course->url() }}" target="_blank" rel="noopener">{{ $row->course->title }}</a>
                                @if ($row->course->is_free)<span class="status status-on">Free</span>@endif
                                <span class="bar-line"><span style="width: {{ round($row->visitors * 100 / $maxCourse) }}%"></span></span>
                            </td>
                            <td class="num"><b>{{ $row->visitors }}</b><span class="row-sub">{{ $row->views }} {{ \Illuminate\Support\Str::plural('view', $row->views) }}</span></td>
                            <td class="num">{{ $row->clicked_buy }}@if ($row->visitors)<span class="row-sub">{{ round($row->clicked_buy * 100 / $row->visitors) }}% of opened</span>@endif</td>
                            <td class="num">{{ $row->paid }}</td>
                            <td class="num">{{ $row->studying }}</td>
                            <td class="num">{{ $time($row->avg_seconds) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>Pages people open</h2><p>Top 15 · {{ strtolower($ranges[$range]) }}</p></div></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Page</th><th class="num">Views</th><th class="num">Visitors</th><th class="num">Time on page</th></tr></thead>
                    <tbody>
                    @foreach ($pages as $page)
                        <tr>
                            <td>
                                <a href="{{ $page->path }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::before((string) $page->title, ' | ') ?: $page->path, 70) }}</a>
                                <span class="row-sub mono">{{ $page->path }}</span>
                                <span class="bar-line"><span style="width: {{ round($page->views * 100 / $maxPage) }}%"></span></span>
                            </td>
                            <td class="num"><b>{{ $page->views }}</b></td>
                            <td class="num">{{ $page->visitors }}</td>
                            <td class="num">{{ $time((int) round((float) $page->avg_seconds)) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="breakdowns">
            @foreach (['Where they come from' => $sources, 'Device' => $devices, 'Browser / app' => $browsers, 'System' => $systems] as $heading => $rows)
                <section class="card">
                    <div class="card-head"><div><h2>{{ $heading }}</h2><p>visitors</p></div></div>
                    <ul class="breakdown">
                        @forelse ($rows as $row)
                            <li>
                                <span class="breakdown-name">{{ $row->name }}</span>
                                <span class="breakdown-value">{{ $row->visitors }} <small>{{ $row->share }}%</small></span>
                                <span class="bar-line"><span style="width: {{ $row->share }}%"></span></span>
                            </li>
                        @empty
                            <li class="muted">—</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    @endif

    <section class="card" id="activity">
        <div class="card-head">
            <div>
                @if ($visitor)
                    <h2>One visitor's path: {{ $activity->first()?->visitorLabel() ?? 'Visitor' }}</h2>
                    <p>Every page this person opened, newest first. <a href="{{ route('admin.analytics', ['range' => $range]) }}#activity">← All activity</a></p>
                @else
                    <h2>Latest activity</h2>
                    <p>The last 60 pages opened. Click a visitor to see their whole path through the site.</p>
                @endif
            </div>
        </div>
        @if ($activity->isEmpty())
            <div class="empty-state"><p>Nothing yet.</p></div>
        @else
            <div class="table-wrap">
                <table class="data activity">
                    <thead><tr><th>When</th><th>Who</th><th>Page</th><th>Time on page</th><th>Device</th><th>From</th></tr></thead>
                    <tbody>
                    @foreach ($activity as $i => $view)
                        @if ($visitor && $i > 0 && $activity[$i - 1]->session_id !== $view->session_id)
                            <tr class="visit-break"><td colspan="6">Earlier visit</td></tr>
                        @endif
                        <tr>
                            <td class="nowrap"><span title="{{ $view->created_at->format('d M Y H:i:s') }}">{{ $view->created_at->format($view->created_at->isToday() ? 'H:i' : 'd M, H:i') }}</span><span class="row-sub">{{ $view->created_at->diffForHumans() }}</span></td>
                            <td class="nowrap">
                                <a href="{{ route('admin.analytics', ['range' => $range, 'visitor' => $view->visitor_id]) }}#activity" @class(['who', 'is-student' => $view->user_id])>{{ $view->visitorLabel() }}</a>
                                @if ($view->user_id)<span class="row-sub">student</span>@endif
                            </td>
                            <td>
                                <a href="{{ $view->path }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::before((string) $view->title, ' | ') ?: $view->path, 60) }}</a>
                                @if ($kindLabels[$view->kind] ?? null)<span @class(['status', 'status-on' => $view->kind === 'checkout', 'status-sched' => $view->kind !== 'checkout'])>{{ $kindLabels[$view->kind] }}</span>@endif
                                <span class="row-sub mono">{{ $view->path }}</span>
                            </td>
                            <td class="nowrap">{{ $time($view->seconds) }}</td>
                            <td class="nowrap">{{ $view->device }}<span class="row-sub">{{ $view->browser }} · {{ $view->os }}</span></td>
                            <td class="nowrap">{{ $view->source }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <p class="muted analytics-note">Counted on this website itself (no Google Analytics). Admins and search-engine bots are not counted, and no IP addresses are kept. "Visitors" are different browsers; a "visit" ends after 30 minutes without opening a page.</p>
</div>
@endsection
