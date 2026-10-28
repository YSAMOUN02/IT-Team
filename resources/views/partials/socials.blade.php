@php
    $socials = social_links();
@endphp
@if ($socials)
    <ul class="socials" aria-label="{{ __('site.follow_us') }}">
        @foreach ($socials as $social)
            <li>
                <a href="{{ $social['url'] }}" target="_blank" rel="noopener me" aria-label="{{ $social['label'] }}" title="{{ $social['label'] }}" style="--brand: {{ $social['color'] }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true">{!! $social['icon'] !!}</svg>
                </a>
            </li>
        @endforeach
    </ul>
@endif
