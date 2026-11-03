{{-- Short labels so all four fit on a phone; the page heading carries the full name. --}}
<nav class="account-tabs" aria-label="{{ __('account.my_courses') }}">
    <a href="{{ route('account.courses') }}" @class(['on' => request()->routeIs('account.courses')]) @if (request()->routeIs('account.courses')) aria-current="page" @endif>{{ __('account.tab_courses') }}</a>
    <a href="{{ route('account.orders') }}" @class(['on' => request()->routeIs('account.orders')]) @if (request()->routeIs('account.orders')) aria-current="page" @endif>{{ __('account.tab_orders') }}</a>
    <a href="{{ route('account.invoices') }}" @class(['on' => request()->routeIs('account.invoices*')]) @if (request()->routeIs('account.invoices*')) aria-current="page" @endif>{{ __('account.tab_invoices') }}</a>
    <a href="{{ route('account.profile') }}" @class(['on' => request()->routeIs('account.profile')]) @if (request()->routeIs('account.profile')) aria-current="page" @endif>{{ __('account.tab_profile') }}</a>
</nav>
