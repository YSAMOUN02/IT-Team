@extends('layouts.site')

@section('title', 'Test payment')
@section('no_ads', true)

@section('content')
    <section class="section">
        <div class="container narrow">
            <div class="card test-gateway">
                <span class="test-badge">TEST MODE · no real money</span>
                <h1>Test payment</h1>
                <p class="lead">This page stands in for ABA PayWay while no merchant account is connected. Choose what the bank answers to see how the website reacts.</p>
                <dl class="summary-lines">
                    <dt>{{ __('account.order_number') }}</dt><dd>{{ $order->number }}</dd>
                    <dt>{{ __('account.course') }}</dt><dd>{{ $order->course_title }}</dd>
                    <dt class="total">{{ __('account.amount') }}</dt><dd class="total">{{ $order->formattedAmount() }}</dd>
                </dl>
                <form method="post" action="{{ route('checkout.test.result', $order) }}" class="btn-row">
                    @csrf
                    <button class="btn btn-primary" name="result" value="APPROVED">✓ Approve payment</button>
                    <button class="btn btn-outline" name="result" value="DECLINED">✗ Decline (card refused)</button>
                    <button class="btn btn-outline" name="result" value="CANCELLED">Cancel</button>
                </form>
                <p class="muted" style="margin-top:20px;font-size:14px">To use the real PayWay checkout, add PAYWAY_MERCHANT_ID and PAYWAY_API_KEY to <code>.env</code>.</p>
            </div>
        </div>
    </section>
@endsection
