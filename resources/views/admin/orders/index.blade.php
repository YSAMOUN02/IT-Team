@extends('admin.layout')

@php
    $tones = ['paid' => 'status-on', 'pending' => 'status-sched', 'review' => 'status-sched', 'failed' => 'status-off', 'cancelled' => 'status-off', 'refunded' => 'status-off'];
@endphp

@section('title', 'Orders')
@section('subtitle', 'Course purchases through ABA PayWay. Revenue from paid orders: '.money_format_simple((float) $revenue).'.')
@section('crumbs')<span aria-hidden="true">/</span><span>Sales</span><span aria-hidden="true">/</span><span>Orders</span>@endsection

@section('content')
    <div class="card reveal">
        <form class="toolbar" method="get">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <div class="search">
                @include('admin.partials.icon', ['name' => 'search'])
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Order no., name, email, course…" aria-label="Search orders">
            </div>
            <nav class="segmented" aria-label="Filter by status">
                <a href="{{ route('admin.orders.index', array_filter(['q' => request('q')])) }}" @class(['on' => ! $status])>All <span class="muted mono">{{ $counts->sum() }}</span></a>
                @foreach (\App\Models\Order::STATUSES as $key => $label)
                    @if ($counts[$key] ?? 0)
                        <a href="{{ route('admin.orders.index', array_filter(['status' => $key, 'q' => request('q')])) }}" @class(['on' => $status === $key])>{{ $label }} <span class="muted mono">{{ $counts[$key] }}</span></a>
                    @endif
                @endforeach
            </nav>
        </form>

        @if ($orders->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'courses'])</div>
                <h3>No orders {{ $status || request('q') ? 'match' : 'yet' }}</h3>
                <p>Orders appear here when students buy a course online.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Order</th><th>Student</th><th>Course</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td><a class="mono" href="{{ route('admin.orders.show', $order) }}"><b>{{ $order->number }}</b></a>@if ($order->gateway === 'test')<span class="row-sub">test payment</span>@endif</td>
                            <td>{{ $order->buyer_name }}<span class="row-sub">{{ $order->buyer_email }}</span></td>
                            <td><span class="row-sub" style="color:var(--text);max-width:260px">{{ $order->course_title }}</span></td>
                            <td class="num"><b>{{ $order->formattedAmount() }}</b></td>
                            <td><span class="status {{ $tones[$order->status] ?? 'status-off' }}">{{ \App\Models\Order::STATUSES[$order->status] }}</span></td>
                            <td class="num">{{ $order->created_at->format('d M Y, H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orders->links('admin.pager') }}
        @endif
    </div>
@endsection
