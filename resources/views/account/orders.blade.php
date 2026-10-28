@extends('layouts.site')

@section('title', __('account.my_orders'))
@section('no_ads', true)

@section('content')
    <section class="account-head">
        <div class="container">
            <p class="eyebrow">{{ auth()->user()->name }}</p>
            <h1>{{ __('account.my_orders') }}</h1>
            @include('account._tabs')
        </div>
    </section>

    <section class="section section-flush-top">
        <div class="container">
            @if ($orders->isEmpty())
                <div class="empty-box">
                    <p>{{ __('account.orders_empty') }}</p>
                    <a class="btn btn-primary" href="{{ route('courses.index') }}">{{ __('account.browse_courses') }}</a>
                </div>
            @else
                <div class="card table-card">
                    <table class="simple-table">
                        <thead><tr><th>{{ __('account.order_number') }}</th><th>{{ __('account.course') }}</th><th>{{ __('account.amount') }}</th><th>{{ __('account.status') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="mono">{{ $order->number }}<small>{{ $order->created_at->format('d/m/Y H:i') }}</small></td>
                                <td>{{ $order->course_title }}</td>
                                <td>{{ $order->formattedAmount() }}</td>
                                <td><span class="pill pill-{{ $order->status }}">{{ __('account.status_'.$order->status) }}</span></td>
                                <td><a href="{{ route('orders.show', $order) }}">→</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
