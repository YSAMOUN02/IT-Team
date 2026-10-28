@extends('layouts.site')

@section('title', __('account.reset_title'))
@section('no_ads', true)

@section('content')
    <section class="section auth-page">
        <div class="container">
            <div class="card auth-box">
                <h1>{{ __('account.reset_title') }}</h1>
                <p class="lead">{{ __('account.reset_text') }}</p>
                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif
                <form method="post" action="{{ route('password.email') }}" class="stack-form">
                    @csrf
                    <label class="field">
                        <span>{{ __('account.email') }}</span>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                        @error('email')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">{{ __('account.send_link') }}</button>
                </form>
                <p class="auth-switch"><a href="{{ route('login') }}">← {{ __('account.sign_in') }}</a></p>
            </div>
        </div>
    </section>
@endsection
