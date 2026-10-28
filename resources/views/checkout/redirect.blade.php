<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('account.redirecting') }}</title>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ @filemtime(public_path('css/site.css')) }}">
</head>
<body class="redirect-page">
    {{-- The signed fields go straight from the browser to ABA PayWay; card details never reach this server. --}}
    <form method="post" action="{{ $action }}" enctype="multipart/form-data" id="payway-form" class="card redirect-card">
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <span class="spinner" aria-hidden="true"></span>
        <h1>{{ __('account.redirecting') }}</h1>
        <p>{{ __('account.redirect_text') }}</p>
        <p class="muted">{{ __('account.order_number') }}: <strong>{{ $order->number }}</strong> · {{ $order->formattedAmount() }}</p>
        <noscript><p>&nbsp;</p></noscript>
        <button type="submit" class="btn btn-primary">{{ __('account.continue_to_payway') }}</button>
    </form>
    <script>document.getElementById('payway-form').submit();</script>
</body>
</html>
