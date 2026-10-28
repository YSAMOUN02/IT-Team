@extends('admin.layout')

@section('title', 'Dashboard')
@section('hide_head', true)

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $messagesTotal = array_sum(array_column($messagesChart, 'value'));
    $publishedTotal = array_sum(array_column($publishedChart, 'value'));
@endphp

@section('content')
<div class="reveal">
    <section class="welcome">
        <div>
            <h1>{{ $greeting }}, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
            <p>{{ now()->format('l, j F Y') }} · Here is what is happening on {{ setting('site_name', config('app.name')) }}.</p>
        </div>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <div class="formula" aria-hidden="true"><span class="fx">fx</span><span class="expr">=SUM(Sales!B:B)&nbsp;→&nbsp;{{ money_format_simple($kpis[0]['value']) }}</span></div>
            <a class="btn btn-ghost" href="{{ route('admin.articles.create', ['type' => 'news']) }}">@include('admin.partials.icon', ['name' => 'plus']) Add news</a>
        </div>
    </section>

    <section class="kpis" aria-label="Summary">
        @foreach ($kpis as $i => $kpi)
            <a href="{{ $kpi['url'] }}" @class(['card', 'kpi', 'alert' => $kpi['alert'] ?? false])>
                <span class="kpi-ref" aria-hidden="true">{{ chr(65 + $i) }}1</span>
                <span class="kpi-icon">@include('admin.partials.icon', ['name' => $kpi['icon']])</span>
                <div class="kpi-label">{{ $kpi['label'] }}</div>
                <div class="kpi-value">{{ ! empty($kpi['money']) ? money_format_simple($kpi['value']) : number_format($kpi['value']) }}</div>
                <div class="kpi-note">
                    @isset($kpi['delta'])
                        @if ($kpi['delta'] > 0)
                            <span class="up">+{{ $kpi['delta'] }}</span> this month
                        @else
                            No new items this month
                        @endif
                    @else
                        {{ $kpi['note'] }}
                    @endisset
                </div>
            </a>
        @endforeach
    </section>

    <div class="dash-grid">
        <div class="stack">
            <div class="charts">
                <section class="card">
                    <div class="card-head">
                        <div><h2>Messages</h2><p>Contact form · last 30 days</p></div>
                    </div>
                    <div class="card-body">
                        <div class="chart-total"><b>{{ $messagesTotal }}</b><span>received</span></div>
                        @include('admin.partials.column-chart', ['data' => $messagesChart, 'unit' => 'messages', 'title' => 'Messages per day, last 30 days', 'periodLabel' => 'Day'])
                    </div>
                </section>
                <section class="card">
                    <div class="card-head">
                        <div><h2>Publishing</h2><p>Tutorials, news &amp; posts · last 12 months</p></div>
                    </div>
                    <div class="card-body">
                        <div class="chart-total"><b>{{ $publishedTotal }}</b><span>published</span></div>
                        @include('admin.partials.column-chart', ['data' => $publishedChart, 'unit' => 'items', 'title' => 'Items published per month, last 12 months', 'periodLabel' => 'Month'])
                    </div>
                </section>
            </div>

            <section class="card">
                <div class="card-head">
                    <div><h2>Recently edited</h2><p>Courses, tutorials, news and posts</p></div>
                </div>
                @if ($recent->isEmpty())
                    <div class="empty-state"><p>Nothing edited yet.</p></div>
                @else
                    <ul class="feed">
                        @foreach ($recent as $item)
                            <li>
                                @if ($item['image'])
                                    <img class="feed-thumb" src="{{ $item['image'] }}" alt="">
                                @else
                                    <span class="feed-thumb">@include('admin.partials.icon', ['name' => 'image'])</span>
                                @endif
                                <div class="feed-main">
                                    <a class="feed-title" href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                    <span class="feed-meta">{{ $item['type'] }} · edited {{ $item['updated_at']->diffForHumans() }}</span>
                                </div>
                                <span class="status {{ $item['published'] ? 'status-on' : 'status-off' }}">{{ $item['published'] ? 'Live' : 'Hidden' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="stack">
            <section class="card">
                <div class="card-head">
                    <div><h2>Quick actions</h2></div>
                </div>
                <div class="card-body quick">
                    <a href="{{ route('admin.articles.create', ['type' => 'news']) }}"><span>@include('admin.partials.icon', ['name' => 'news'])</span> Add news</a>
                    <a href="{{ route('admin.articles.create', ['type' => 'tutorial']) }}"><span>@include('admin.partials.icon', ['name' => 'tutorials'])</span> Add tutorial</a>
                    <a href="{{ route('admin.courses.create') }}"><span>@include('admin.partials.icon', ['name' => 'courses'])</span> Add course</a>
                    <a href="{{ route('admin.testimonials.create') }}"><span>@include('admin.partials.icon', ['name' => 'quote'])</span> Add testimonial</a>
                    <a href="{{ route('admin.pages.index') }}"><span>@include('admin.partials.icon', ['name' => 'pages'])</span> Edit pages</a>
                    <a href="{{ route('admin.settings.edit') }}"><span>@include('admin.partials.icon', ['name' => 'phone'])</span> Contact details</a>
                </div>
            </section>

            <section class="card">
                <div class="card-head">
                    <div><h2>Latest messages</h2><p>From the contact form</p></div>
                    <a href="{{ route('admin.inquiries.index') }}">View all</a>
                </div>
                @if ($inquiries->isEmpty())
                    <div class="empty-state">
                        <div class="ico">@include('admin.partials.icon', ['name' => 'inbox'])</div>
                        <p>No messages yet. They will appear here when visitors use the contact form.</p>
                    </div>
                @else
                    <ul class="feed">
                        @foreach ($inquiries as $inquiry)
                            <li @class(['unread' => ! $inquiry->read_at])>
                                <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($inquiry->name, 0, 1)) }}</span>
                                <div class="feed-main">
                                    <a class="feed-title" href="{{ route('admin.inquiries.show', $inquiry) }}">{{ $inquiry->subject }}</a>
                                    <span class="feed-meta">{{ $inquiry->name }} · {{ $inquiry->created_at->diffForHumans() }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
