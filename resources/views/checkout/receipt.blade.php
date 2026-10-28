@extends('layouts.site')

@section('title', __('account.receipt_title', ['number' => $order->number]))
@section('no_ads', true)

@push('head')
    @if ($order->isPending())
        <meta http-equiv="refresh" content="6">
    @endif
@endpush

@php
    $tone = ['paid' => 'ok', 'pending' => 'wait', 'review' => 'wait', 'failed' => 'bad', 'cancelled' => 'bad', 'refunded' => 'bad'][$order->status] ?? 'wait';
@endphp

@section('content')
    <section class="section">
        <div class="container narrow">
            <div class="card receipt receipt-{{ $tone }}">
                <div class="receipt-icon" aria-hidden="true">
                    @if ($tone === 'ok')
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                    @elseif ($tone === 'wait')
                        <span class="spinner"></span>
                    @else
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9 9l6 6M15 9l-6 6"/></svg>
                    @endif
                </div>
                <h1>{{ __('account.status_'.$order->status) }}</h1>
                <p class="lead">{{ __('account.'.$order->status.'_text') }}</p>

                <dl class="summary-lines">
                    <dt>{{ __('account.order_number') }}</dt><dd class="mono">{{ $order->number }}</dd>
                    <dt>{{ __('account.course') }}</dt><dd>{{ $order->course_title }}</dd>
                    @if ($order->plan_label)
                        <dt>{{ __('account.plan') }}</dt><dd>{{ $order->plan_label }}</dd>
                    @endif
                    @if ($order->discount_percent)
                        <dt>{{ __('account.discount') }}</dt><dd>−{{ $order->discount_percent }}% <s class="muted">{{ money_format_simple((float) $order->original_amount, $order->currency) }}</s></dd>
                    @endif
                    @if ($order->isPaid())
                        <dt>{{ __('account.paid_on') }}</dt><dd>{{ $order->paid_at?->format('d/m/Y H:i') }}</dd>
                        @if ($order->enrollment)
                            <dt>{{ __('account.access') }}</dt>
                            <dd>{{ $order->enrollment->expires_at ? __('account.expires', ['date' => $order->enrollment->expires_at->format('d/m/Y')]) : __('account.access_lifetime') }}</dd>
                        @endif
                    @endif
                    <dt class="total">{{ __('account.amount') }}</dt><dd class="total">{{ $order->formattedAmount() }}</dd>
                </dl>

                <div class="btn-row btn-row-center">
                    @if ($order->isPaid() && $order->course)
                        <a class="btn btn-primary btn-lg" href="{{ route('learn.course', $order->course->slug) }}">{{ __('account.start_learning') }}</a>
                    @elseif ($order->isPending())
                        <a class="btn btn-outline" href="{{ route('orders.show', $order) }}">{{ __('account.check_again') }}</a>
                    @elseif (in_array($order->status, ['failed', 'cancelled']) && $order->course)
                        <a class="btn btn-primary" href="{{ route('checkout.show', $order->course->slug) }}">{{ __('account.try_again') }}</a>
                    @endif
                    <a class="btn btn-outline" href="{{ route('account.orders') }}">{{ __('account.my_orders') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
