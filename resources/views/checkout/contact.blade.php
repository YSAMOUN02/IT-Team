@extends('layouts.site')

{{-- Online payment is switched off (Admin > Settings > Payments): show the price and how to get in touch. --}}
@section('title', __('account.contact_to_buy').' · '.$course->title)
@section('no_ads', true)

@php
    $phone = setting('phone');
@endphp

@section('content')
<section class="section checkout">
    <div class="container narrow">
        <p class="eyebrow"><a href="{{ $course->url() }}">{{ $course->title }}</a></p>
        <h1>{{ __('account.contact_to_buy') }}</h1>

        <div class="card contact-buy">
            <div class="contact-buy-icon" aria-hidden="true">
                @if ($telegram)
                    <svg viewBox="0 0 24 24"><path d="M21.5 4.5 2.9 11.7c-1 .4-1 1.8.1 2.1l4.6 1.4 1.8 5.6c.3.9 1.4 1.1 2 .4l2.6-2.7 4.6 3.4c.8.6 1.9.1 2.1-.9l3-14.6c.2-1.2-1-2.2-2.2-1.7ZM9.6 14.6l8.4-7.4-6.8 8.6-.3 3-1.3-4.2Z"/></svg>
                @else
                    <svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4V5Z"/></svg>
                @endif
            </div>
            <p class="lead">{{ __('account.payments_off_text') }}</p>

            @if ($plans->isNotEmpty())
                <dl class="summary-lines contact-buy-plans">
                    @foreach ($plans as $plan)
                        <dt>{{ $plan->periodLabel() }}@if ($plan->discountActive()) <b class="disc">−{{ $plan->discount_percent }}%</b>@endif</dt>
                        <dd>@if ($plan->discountActive())<s class="muted">{{ $plan->formattedOriginalPrice() }}</s> @endif<strong>{{ $plan->formattedPrice() }}</strong></dd>
                    @endforeach
                </dl>
            @endif

            <ol class="contact-buy-steps">
                <li>{{ __('account.payments_off_step1') }}</li>
                <li>{{ __('account.payments_off_step2') }}</li>
                <li>{{ __('account.payments_off_step3') }}</li>
            </ol>

            <div class="contact-buy-actions">
                <a class="btn btn-primary btn-lg {{ $telegram ? 'btn-telegram' : '' }}" href="{{ $contactUrl }}" target="_blank" rel="noopener">
                    {{ $telegram ? __('account.contact_telegram') : __('site.contact_us') }}
                </a>
                @if ($phone)
                    <a class="btn btn-outline btn-lg" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">📞 {{ $phone }}</a>
                @endif
            </div>
            <p class="muted contact-buy-note">{{ __('account.payments_off_note', ['course' => $course->title]) }}</p>
        </div>
    </div>
</section>
@endsection
