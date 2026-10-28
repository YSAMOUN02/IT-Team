@php($logo = media_url(setting('logo')))
<a class="logo {{ ($light ?? false) ? 'logo-light' : '' }}" href="{{ route('home') }}" aria-label="{{ setting('site_name', config('app.name')) }}">
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ setting('site_name', config('app.name')) }}">
    @else
        <span class="logo-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32"><rect x="3" y="3" width="26" height="26" rx="7"/><path d="M10 11h12M16 11v11"/></svg>
        </span>
        <span class="logo-text">{{ setting('logo_text', 'TEAMIT') }}<span>{{ setting('logo_text_accent', 'Cambodia') }}</span></span>
    @endif
</a>
