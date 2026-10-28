{{-- New student account. Used on /register and on the sign-in step before buying. --}}
@php($mine = old('form', 'register') === 'register')
<form method="post" action="{{ route('register.store') }}" class="stack-form">
    @csrf
    <input type="hidden" name="form" value="register">
    <label class="field">
        <span>{{ __('account.name') }} *</span>
        <input type="text" name="name" value="{{ $mine ? old('name') : '' }}" required maxlength="120" autocomplete="name" @if ($autofocus ?? false) autofocus @endif>
        @if ($mine) @error('name')<small class="field-error">{{ $message }}</small>@enderror @endif
    </label>
    <label class="field">
        <span>{{ __('account.email') }} *</span>
        <input type="email" name="email" value="{{ $mine ? old('email') : '' }}" required maxlength="190" autocomplete="email">
        @if ($mine) @error('email')<small class="field-error">{{ $message }}</small>@enderror @endif
    </label>
    <label class="field">
        <span>{{ __('account.phone') }}</span>
        <input type="tel" name="phone" value="{{ $mine ? old('phone') : '' }}" maxlength="40" autocomplete="tel">
    </label>
    <div class="form-grid">
        <label class="field">
            <span>{{ __('account.password') }} *</span>
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
            @if ($mine) @error('password')<small class="field-error">{{ $message }}</small>@enderror @endif
        </label>
        <label class="field">
            <span>{{ __('account.password_confirm') }} *</span>
            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </label>
    </div>
    <button type="submit" class="btn btn-primary btn-block">{{ __('account.create_account') }}</button>
</form>
