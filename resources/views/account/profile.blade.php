@extends('layouts.site')

@section('title', __('account.profile'))
@section('no_ads', true)

@section('content')
    <section class="account-head">
        <div class="container">
            <p class="eyebrow">{{ $user->name }}</p>
            <h1>{{ __('account.profile') }}</h1>
            @include('account._tabs')
        </div>
    </section>

    <section class="section section-flush-top">
        <div class="container narrow">
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            <form method="post" action="{{ route('account.profile.update') }}" class="card form-card stack-form">
                @csrf @method('PUT')
                <div class="form-grid">
                    <label class="field">
                        <span>{{ __('account.name') }} *</span>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name">
                        @error('name')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">
                        <span>{{ __('account.phone') }}</span>
                        <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="40" autocomplete="tel">
                    </label>
                    <label class="field field-full">
                        <span>{{ __('account.email') }} *</span>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190" autocomplete="email">
                        @error('email')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>

                <h2 class="label-heading">{{ __('account.change_password') }}</h2>
                <p class="muted">{{ $user->password ? __('account.change_password_text') : __('account.google_account_note') }}</p>
                <div class="form-grid">
                    @if ($user->password)
                        <label class="field field-full">
                            <span>{{ __('account.current_password') }}</span>
                            <input type="password" name="current_password" autocomplete="current-password">
                            @error('current_password')<small class="field-error">{{ $message }}</small>@enderror
                        </label>
                    @endif
                    <label class="field">
                        <span>{{ __('account.new_password') }}</span>
                        <input type="password" name="password" minlength="8" autocomplete="new-password">
                        @error('password')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">
                        <span>{{ __('account.password_confirm') }}</span>
                        <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('account.save') }}</button>
            </form>
        </div>
    </section>
@endsection
