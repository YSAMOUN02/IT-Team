{{-- Email + password sign-in. Used on /login and on the sign-in step before buying. --}}
<form method="post" action="{{ route('login.store') }}" class="stack-form">
    @csrf
    <input type="hidden" name="form" value="login">
    <label class="field">
        <span>{{ __('account.email') }}</span>
        <input type="email" name="email" value="{{ old('form', 'login') === 'login' ? old('email') : '' }}" required autocomplete="username" @if ($autofocus ?? false) autofocus @endif>
        @if (old('form', 'login') === 'login')
            @error('email')<small class="field-error">{{ $message }}</small>@enderror
        @endif
    </label>
    <label class="field">
        <span>{{ __('account.password') }}</span>
        <input type="password" name="password" required autocomplete="current-password">
    </label>
    <div class="auth-row">
        <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('account.remember') }}</label>
        <a href="{{ route('password.request') }}">{{ __('account.forgot') }}</a>
    </div>
    <button type="submit" class="btn btn-primary btn-block">{{ __('account.sign_in') }}</button>
</form>
