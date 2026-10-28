@extends('admin.layout')

@php
    $tones = ['paid' => 'status-on', 'pending' => 'status-sched', 'review' => 'status-sched'];
@endphp

@section('title', 'Order '.$order->number)
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.orders.index') }}">Orders</a>
    <span aria-hidden="true">/</span><span>{{ $order->number }}</span>
@endsection
@section('actions')
    @if (in_array($order->status, ['pending', 'review']))
        <form method="post" action="{{ route('admin.orders.recheck', $order) }}">
            @csrf
            <button class="btn btn-primary">@include('admin.partials.icon', ['name' => 'search']) Check with PayWay</button>
        </form>
    @endif
    @if ($order->user && ! $order->user->isAdmin())
        <a class="btn btn-ghost" href="{{ route('admin.students.show', $order->user) }}">@include('admin.partials.icon', ['name' => 'user']) Student</a>
    @endif
    @if ($order->isPaid())
        <form method="post" action="{{ route('admin.orders.refund', $order) }}" onsubmit="return confirm('Mark this order as refunded? The course access it paid for is removed. (Send the money back in the PayWay merchant portal first.)')">
            @csrf
            <button class="btn btn-ghost">@include('admin.partials.icon', ['name' => 'alert']) Mark as refunded</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="form-grid reveal">
        <div>
            @if ($order->status === 'review')
                <div class="alert alert-error">@include('admin.partials.icon', ['name' => 'alert']) <div><strong>Needs review.</strong> PayWay reports a payment that does not match this order (amount or currency). Check it in the PayWay merchant portal, then give access from the student page if it is correct.</div></div>
            @endif
            <section class="card">
                <div class="card-head">
                    <div><h2>{{ $order->course_title }}</h2><p>{{ $order->created_at->format('l, j F Y \a\t H:i') }}</p></div>
                    <span class="status {{ $tones[$order->status] ?? 'status-off' }}">{{ \App\Models\Order::STATUSES[$order->status] }}</span>
                </div>
                <div class="card-body">
                    <dl class="meta-list wide">
                        <dt>Amount</dt><dd><b>{{ $order->formattedAmount() }}</b>@if ($order->discount_percent) <span class="muted">(−{{ $order->discount_percent }}% from {{ money_format_simple((float) $order->original_amount, $order->currency) }})</span>@endif</dd>
                        <dt>Plan</dt><dd>{{ $order->plan_label ?? '—' }}</dd>
                        <dt>Access bought</dt><dd>{{ $order->access_days ? $order->access_days.' days' : 'Lifetime' }}</dd>
                        <dt>Payment method</dt><dd>{{ ['cards' => 'Card', 'abapay_khqr' => 'ABA KHQR', '' => 'Chosen on PayWay'][$order->payment_option ?? ''] ?? $order->payment_option }}</dd>
                        <dt>Gateway</dt><dd>{{ $order->gateway === 'test' ? 'Test payment (no real money)' : 'ABA PayWay' }}</dd>
                        <dt>PayWay status</dt><dd>{{ $order->gateway_status ?? '—' }}</dd>
                        <dt>Approval code</dt><dd>{{ $order->apv ?? '—' }}</dd>
                        <dt>Paid at</dt><dd>{{ $order->paid_at?->format('d M Y, H:i') ?? '—' }}</dd>
                        <dt>Last checked</dt><dd>{{ $order->checked_at?->diffForHumans() ?? 'never' }}</dd>
                    </dl>
                </div>
            </section>
            @if ($order->gateway_payload)
                <section class="card">
                    <details class="card-body">
                        <summary class="mono" style="cursor:pointer">Gateway response (technical)</summary>
                        <pre class="payload">{{ json_encode($order->gateway_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                </section>
            @endif
        </div>
        <div class="form-side">
            <section class="card">
                <div class="card-head"><h2>Buyer</h2></div>
                <div class="card-body">
                    <dl class="meta-list">
                        <dt>Name</dt><dd>{{ $order->buyer_name }}</dd>
                        <dt>Email</dt><dd><a href="mailto:{{ $order->buyer_email }}">{{ $order->buyer_email }}</a></dd>
                        <dt>Phone</dt><dd>{{ $order->buyer_phone ?: '—' }}</dd>
                    </dl>
                </div>
            </section>
            @if ($order->enrollment)
                <section class="card">
                    <div class="card-head"><h2>Access</h2></div>
                    <div class="card-body">
                        <dl class="meta-list">
                            <dt>From</dt><dd>{{ $order->enrollment->starts_at->format('d M Y') }}</dd>
                            <dt>Until</dt><dd>{{ $order->enrollment->expires_at?->format('d M Y') ?? 'Lifetime' }}</dd>
                        </dl>
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
