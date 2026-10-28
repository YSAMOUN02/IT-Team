@extends('layouts.site')

@section('title', __('account.reset_title'))
@section('no_ads', true)

@section('content')
    <section class="section auth-page">
        <div class="container">
            <div class="card auth-box">
                <h1>{{ __('account.reset_title') }}</h1>
                <form method="post" action="{{ route('password.update') }}" class="stack-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <label class="field">
                        <span>{{ __('account.email') }}</span>
                        <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
                        @error('email')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">
                        <span>{{ __('account.new_password') }}</span>
                        <input type="password" name="password" required minlength="8" autocomplete="new-password">
                        @error('password')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">
                        <span>{{ __('account.password_confirm') }}</span>
                        <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">{{ __('account.save_password') }}</button>
                </form>
            </div>
        </div>
    </section>
@endsection
