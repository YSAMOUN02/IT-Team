{{--
    @include('partials.ad', ['placement' => 'article_top'])
    Off -> nothing at all. On with nothing to show yet -> "ad space" placeholder.
    On with your own image -> the banner. On with a Google ad unit -> the ad.
    (Own banners use "promo" class names: ad blockers hide anything called "ad-…".)
--}}
@unless (View::hasSection('no_ads'))
    @php
        $adState = \App\Support\Ads::state($placement);
        $banner = $adState === 'live' ? \App\Support\Ads::banner($placement) : null;
        // Width/height keep the banner's space while it loads, so the page does not jump.
        $size = fn ($w, $h) => $w ? ' width="'.(int) $w.'" height="'.(int) $h.'"' : '';
    @endphp
    @if ($banner)
        <aside class="promo promo-{{ str_replace('_', '-', $placement) }} {{ $class ?? '' }}" aria-label="{{ __('site.advertisement') }}">
            <span class="promo-label">{{ __('site.advertisement') }}</span>
            @if ($banner['link'])
                <a href="{{ route('promo.go', $placement) }}"{!! $banner['external'] ? ' target="_blank" rel="sponsored noopener"' : '' !!}>
            @endif
                <picture>
                    @if ($banner['phone'])
                        <source media="(max-width: 640px)" srcset="{{ $banner['phone'] }}"{!! $size($banner['phone_width'], $banner['phone_height']) !!}>
                    @endif
                    <img src="{{ $banner['image'] }}" alt="{{ $banner['alt'] }}"{!! $size($banner['width'], $banner['height']) !!} loading="{{ $placement === 'header' ? 'eager' : 'lazy' }}" decoding="async">
                </picture>
            @if ($banner['link'])
                </a>
            @endif
        </aside>
    @elseif ($adState === 'live')
        <aside class="ad-slot ad-{{ $placement }} {{ $class ?? '' }}" aria-label="{{ __('site.advertisement') }}">
            <span class="ad-label">{{ __('site.advertisement') }}</span>
            {!! \App\Support\Ads::slotHtml($placement) !!}
        </aside>
    @elseif ($adState === 'placeholder')
        <aside class="ad-slot ad-{{ $placement }} {{ $class ?? '' }}" aria-label="{{ __('site.advertisement') }}">
            <a class="ad-placeholder ad-fmt-{{ \App\Support\Ads::format($placement) }}" href="{{ route('contact') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m7 15 2.5-6 2.5 6M7.8 13h3.4M15 9v6h1.5a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2z"/></svg>
                <span>
                    <strong>{{ __('site.ad_placeholder_title') }}</strong>
                    <small>{{ __('site.ad_placeholder_text') }}</small>
                </span>
            </a>
        </aside>
    @endif
@endunless
