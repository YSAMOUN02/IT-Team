@extends('admin.layout')

@section('title', 'Subscribers')
@section('subtitle', 'People who signed up for the newsletter in the website footer.')
@section('crumbs')<span aria-hidden="true">/</span><span>Overview</span><span aria-hidden="true">/</span><span>Subscribers</span>@endsection
@section('actions')
    <a class="btn btn-primary" href="{{ route('admin.subscribers.export') }}">@include('admin.partials.icon', ['name' => 'upload']) Export CSV ({{ $totals['active'] }})</a>
@endsection

@section('content')
    <div class="card reveal">
        <form class="toolbar" method="get">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="search">
                @include('admin.partials.icon', ['name' => 'search'])
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search emails…" aria-label="Search emails">
            </div>
            <nav class="segmented" aria-label="Filter by status">
                @foreach (['all' => 'All', 'active' => 'Active', 'unsubscribed' => 'Unsubscribed'] as $key => $text)
                    <a href="{{ route('admin.subscribers.index', array_filter(['status' => $key, 'q' => request('q')])) }}" @class(['on' => $status === $key])>{{ $text }} <span class="muted mono">{{ $totals[$key] }}</span></a>
                @endforeach
            </nav>
            <span class="toolbar-count">{{ $subscribers->total() }} {{ \Illuminate\Support\Str::plural('email', $subscribers->total()) }}</span>
        </form>

        @if ($subscribers->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'mail'])</div>
                <h3>{{ request('q') || $status !== 'all' ? 'Nothing matches' : 'No subscribers yet' }}</h3>
                <p>Sign-ups from the newsletter form in the website footer appear here.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Email</th><th>Status</th><th>Signed up</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($subscribers as $subscriber)
                        <tr>
                            <td>
                                <div class="row-title">
                                    <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($subscriber->email, 0, 1)) }}</span>
                                    <a href="mailto:{{ $subscriber->email }}">{{ $subscriber->email }}</a>
                                </div>
                            </td>
                            <td>
                                @if ($subscriber->unsubscribed_at)
                                    <span class="status status-off" title="{{ $subscriber->unsubscribed_at->format('d M Y') }}">Unsubscribed</span>
                                @else
                                    <span class="status status-on">Active</span>
                                @endif
                            </td>
                            <td class="num">{{ $subscriber->created_at->format('d M Y, H:i') }}</td>
                            <td class="actions">
                                <form method="post" action="{{ route('admin.subscribers.destroy', $subscriber) }}" data-confirm="Remove {{ $subscriber->email }} from the list?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm btn-icon" title="Remove" aria-label="Remove">@include('admin.partials.icon', ['name' => 'trash'])</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $subscribers->links('admin.pager') }}
        @endif
    </div>
@endsection
