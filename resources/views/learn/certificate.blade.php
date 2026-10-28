@extends('layouts.site')

@section('title', __('account.certificate_title').' · '.$course->title)
@section('no_ads', true)
@section('body_class', 'page-certificate')

@section('content')
    <section class="section">
        <div class="container">
            @if (! empty($verifying))
                <div class="alert alert-success cert-verified">✓ {{ __('account.verified') }}</div>
            @else
                <div class="btn-row cert-actions">
                    <a class="btn btn-outline" href="{{ route('learn.course', $course->slug) }}">← {{ __('account.back_to_course') }}</a>
                    <button type="button" class="btn btn-primary" onclick="window.print()">{{ __('account.print') }}</button>
                </div>
            @endif

            <article class="certificate">
                <div class="cert-inner">
                    <div class="cert-brand">
                        @include('partials.logo')
                    </div>
                    <p class="cert-kicker">{{ __('account.certificate_title') }}</p>
                    <p class="cert-small">{{ __('account.certifies') }}</p>
                    <h1 class="cert-name">{{ $user->name }}</h1>
                    <p class="cert-small">{{ __('account.has_completed') }}</p>
                    <h2 class="cert-course">{{ $course->title }}</h2>
                    <div class="cert-foot">
                        <div><small>{{ __('account.issued') }}</small><strong>{{ $enrollment->completed_at?->format('d/m/Y') }}</strong></div>
                        <div class="cert-seal" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-2.5 5 2.5-1.5-7"/></svg>
                        </div>
                        <div><small>{{ __('account.certificate_code') }}</small><strong class="mono">{{ $enrollment->certificate_code }}</strong></div>
                    </div>
                    <p class="cert-verify">{{ __('account.verify_at') }} {{ route('certificates.verify', $enrollment->certificate_code) }}</p>
                </div>
            </article>
        </div>
    </section>
@endsection
