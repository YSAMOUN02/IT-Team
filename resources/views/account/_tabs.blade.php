<nav class="account-tabs" aria-label="{{ __('account.my_courses') }}">
    <a href="{{ route('account.courses') }}" @class(['on' => request()->routeIs('account.courses')])>{{ __('account.my_courses') }}</a>
    <a href="{{ route('account.orders') }}" @class(['on' => request()->routeIs('account.orders')])>{{ __('account.my_orders') }}</a>
    <a href="{{ route('account.profile') }}" @class(['on' => request()->routeIs('account.profile')])>{{ __('account.profile') }}</a>
</nav>
