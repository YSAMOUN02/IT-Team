<form class="contact-form" method="post" action="{{ route('contact.store') }}#contact-form" id="contact-form">
    @csrf
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Spam trap: hidden from people, filled in by bots. --}}
    <div class="hp" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <div class="form-grid">
        <label class="field">
            <span>{{ __('site.form_name') }} *</span>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
        </label>
        <label class="field">
            <span>{{ __('site.form_email') }} *</span>
            <input type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email">
        </label>
        <label class="field">
            <span>{{ __('site.form_phone') }}</span>
            <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel">
        </label>
        <label class="field">
            <span>{{ __('site.form_subject') }} *</span>
            <input type="text" name="subject" value="{{ old('subject', $subject ?? '') }}" required maxlength="190">
        </label>
        <label class="field field-full">
            <span>{{ __('site.form_message') }} *</span>
            <textarea name="message" rows="5" required maxlength="5000">{{ old('message') }}</textarea>
        </label>
    </div>
    <button type="submit" class="btn btn-primary">{{ __('site.form_submit') }}</button>
</form>
