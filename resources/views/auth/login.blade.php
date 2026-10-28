@extends('layouts.site')

@section('title', __('account.login_title'))
@section('no_ads', true)

@section('content')
    <section class="section auth-page">
        <div class="container">
            <div class="card auth-box">
                <h1>{{ __('account.login_title') }}</h1>
                <p class="lead">{{ __('account.login_text') }}</p>
                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif
                @include('auth._google')
                @include('auth._login-form', ['autofocus' => true])
                <p class="auth-switch">{{ __('account.no_account') }} <a href="{{ route('register') }}">{{ __('account.create_account') }}</a></p>
            </div>
        </div>
    </section>
@endsection
