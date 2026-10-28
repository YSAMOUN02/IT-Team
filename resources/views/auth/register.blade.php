@extends('layouts.site')

@section('title', __('account.register_title'))
@section('no_ads', true)

@section('content')
    <section class="section auth-page">
        <div class="container">
            <div class="card auth-box">
                <h1>{{ __('account.register_title') }}</h1>
                <p class="lead">{{ __('account.register_text') }}</p>
                @include('auth._google')
                @include('auth._register-form', ['autofocus' => true])
                <p class="auth-switch">{{ __('account.have_account') }} <a href="{{ route('login') }}">{{ __('account.sign_in') }}</a></p>
            </div>
        </div>
    </section>
@endsection
