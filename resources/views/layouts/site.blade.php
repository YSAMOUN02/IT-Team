@php
    $siteName = setting('site_name', config('app.name'));
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle ? $pageTitle.' | '.$siteName : $siteName;
    // Page description, else the site tagline, else the home page introduction.
    $description = trim($__env->yieldContent('description')) ?: (string) setting('tagline');
    if (trim(strip_tags($description)) === '') {
        $description = (string) \App\Models\Page::findBySlug('home')?->field('hero.text');
    }
    $description = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($description)))), 157);
    $favicon = media_url(setting('favicon'));
    $analytics = setting('google_analytics_id');
    $shareImage = trim($__env->yieldContent('og_image')) ?: \App\Support\Seo::defaultImage();
    $canonical = url()->current().(request()->integer('page') > 1 ? '?page='.request()->integer('page') : '');
    // Private or thin pages stay out of search results (links on them are still followed).
    $private = request()->is('login', 'register', 'forgot-password', 'reset-password/*', 'my/*', 'checkout/*', 'orders/*', 'courses/*/checkout', 'courses/*/enroll', 'learn/*', 'search', 'newsletter/*', 'certificates/*');
    $robots = trim($__env->yieldContent('robots')) ?: ($private ? 'noindex, follow' : 'index, follow, max-image-preview:large');
    $locale = app()->getLocale() === 'km' ? 'km_KH' : 'en_US';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @unless (auth()->user()?->isAdmin()) data-track="{{ route('stats.view') }}" data-track-leave="{{ route('stats.leave') }}" @endunless>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="theme-color" content="#343090">

    {{-- Link previews (Facebook, Telegram, LINE, X…) --}}
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $pageTitle ?: $siteName }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ $locale }}">
    @if ($shareImage)
        <meta property="og:image" content="{{ $shareImage }}">
        <meta name="twitter:image" content="{{ $shareImage }}">
    @endif
    <meta name="twitter:card" content="{{ $shareImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $pageTitle ?: $siteName }}">
    @if ($description)<meta name="twitter:description" content="{{ $description }}">@endif

    @if (setting('google_site_verification'))
        <meta name="google-site-verification" content="{{ setting('google_site_verification') }}">
    @endif
    @if (setting('bing_site_verification'))
        <meta name="msvalidate.01" content="{{ setting('bing_site_verification') }}">
    @endif

    {{-- Motion: mark the page before first paint so scroll animations never flash. --}}
    <script>if(!matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('motion');</script>
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Kantumruy+Pro:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ @filemtime(public_path('css/site.css')) }}">
    @if ($adsPublisher = \App\Support\Ads::publisherId())
        <meta name="google-adsense-account" content="{{ $adsPublisher }}">
        @unless (View::hasSection('no_ads'))
            {!! \App\Support\Ads::headScript() !!}
        @endunless
    @endif
    @if ($analytics)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analytics }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',@json($analytics));</script>
    @endif
    @if (request()->routeIs('home'))
        {!! \App\Support\Seo::jsonLd(['@context' => 'https://schema.org', '@graph' => [\App\Support\Seo::organization(), \App\Support\Seo::website()]]) !!}
    @endif
    @stack('schema')
    @stack('head')
</head>
<body class="@yield('body_class')">
<a class="skip-link" href="#main">Skip to content</a>
@if (auth()->user()?->isAdmin())
    @include('partials.admin-bar')
@endif

<header class="site-header" data-header>
    <div class="container header-inner">
        @include('partials.logo')

        <nav class="main-nav" aria-label="Main">
            <ul>
                @foreach (config('navigation') as $item)
                    @include('partials.nav-item', ['item' => $item])
                @endforeach
            </ul>
        </nav>

        <div class="header-actions">
            <button type="button" class="icon-btn" data-search-open aria-label="{{ __('site.search') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </button>
            @auth
                <details class="account-menu">
                    <summary class="icon-btn" aria-label="{{ __('account.my_courses') }}">
                        @if (auth()->user()->avatar)
                            <img class="account-avatar" src="{{ auth()->user()->avatar }}" alt="" referrerpolicy="no-referrer">
                        @else
                            <span class="account-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        @endif
                    </summary>
                    <div class="account-pop">
                        <strong>{{ auth()->user()->name }}</strong>
                        <a href="{{ route('account.courses') }}">{{ __('account.my_courses') }}</a>
                        <a href="{{ route('account.orders') }}">{{ __('account.my_orders') }}</a>
                        <a href="{{ route('account.profile') }}">{{ __('account.profile') }}</a>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}">Admin</a>
                        @endif
                        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">{{ __('account.logout') }}</button></form>
                    </div>
                </details>
            @else
                <a class="icon-btn" href="{{ route('login') }}" aria-label="{{ __('account.sign_in') }}" title="{{ __('account.sign_in') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                </a>
            @endauth
            @if (setting('header_button_label'))
                <a class="btn btn-primary btn-sm header-cta" href="{{ link_url(setting('header_button_url', '/contact')) }}">{{ setting('header_button_label') }}</a>
            @endif
            <button type="button" class="icon-btn menu-toggle" data-menu-open aria-label="{{ __('site.menu') }}" aria-expanded="false" aria-controls="mobile-menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>
</header>

{{-- Mobile menu --}}
<div class="mobile-menu" id="mobile-menu" data-menu hidden>
    <div class="mobile-menu-panel">
        <div class="mobile-menu-head">
            @include('partials.logo')
            <button type="button" class="icon-btn" data-menu-close aria-label="{{ __('site.close') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <ul class="mobile-nav">
            @foreach (config('navigation') as $item)
                @foreach (isset($item['children']) ? $item['children'] : [$item] as $link)
                    <li><a href="{{ route($link['route']) }}" @class(['active' => request()->routeIs($link['active'] ?? $link['route'])])>{{ $link['label'] }}</a></li>
                @endforeach
            @endforeach
        </ul>
        @if (setting('header_button_label'))
            <a class="btn btn-primary btn-block" href="{{ link_url(setting('header_button_url', '/contact')) }}">{{ setting('header_button_label') }}</a>
        @endif
        @auth
            <a class="mobile-signin" href="{{ route('account.courses') }}">{{ __('account.my_courses') }}</a>
        @else
            <a class="mobile-signin" href="{{ route('login') }}">{{ __('site.sign_in') }}</a>
        @endauth
    </div>
</div>

{{-- Search overlay --}}
<div class="search-overlay" data-search hidden>
    <form class="search-box" action="{{ route('search') }}" method="get" role="search">
        <label class="sr-only" for="site-search">{{ __('site.search') }}</label>
        <input id="site-search" type="search" name="s" value="{{ request('s') }}" placeholder="{{ __('site.search_placeholder') }}" autocomplete="off">
        <button type="submit" class="btn btn-primary">{{ __('site.search') }}</button>
        <button type="button" class="icon-btn" data-search-close aria-label="{{ __('site.close') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </form>
</div>

<main id="main">
    {{-- On the home page this ad slot sits under the hero instead (see home.blade.php). --}}
    @unless (request()->routeIs('home'))
        @include('partials.ad', ['placement' => 'header', 'class' => 'container'])
    @endunless
    @yield('content')
    @include('partials.ad', ['placement' => 'footer', 'class' => 'container'])
</main>

<footer class="site-footer">
    @php
        // Footer menu: every page from the top menu, plus Contact (which is only down here).
        $footerLinks = collect(config('navigation'))->flatMap(fn ($item) => $item['children'] ?? [$item])
            ->push(['label' => 'Contact', 'route' => 'contact'])->unique('route');
    @endphp
    <div class="container footer-grid">
        <div class="footer-brand">
            @include('partials.logo', ['light' => true])
            @if (setting('footer_text') || setting('tagline'))
                <p>{{ setting('footer_text', setting('tagline')) }}</p>
            @endif
            @include('partials.socials')
        </div>

        <nav class="footer-col" aria-label="{{ __('site.menu') }}">
            <h2 class="footer-title">{{ __('site.menu') }}</h2>
            <ul class="footer-links">
                @foreach ($footerLinks as $link)
                    <li><a href="{{ route($link['route']) }}" @class(['active' => request()->routeIs($link['active'] ?? $link['route'])])>{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>

        @if ($footerCourses->isNotEmpty())
            <div class="footer-col">
                <h2 class="footer-title">{{ __('site.courses') }}</h2>
                <ul class="footer-links">
                    @foreach ($footerCourses as $course)
                        <li><a href="{{ $course->url() }}">{{ $course->title }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="footer-col">
            <form class="newsletter" id="newsletter" method="post" action="{{ route('newsletter.store') }}">
                @csrf
                <h2 class="footer-title">{{ setting('newsletter_title', __('site.newsletter_title')) }}</h2>
                <p>{{ setting('newsletter_text', __('site.newsletter_text')) }}</p>
                <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="newsletter-row">
                    <label class="sr-only" for="newsletter-email">{{ __('site.form_email') }}</label>
                    <input id="newsletter-email" type="email" name="email" required maxlength="190" autocomplete="email"
                           placeholder="{{ __('site.newsletter_placeholder') }}" value="{{ old('email') && $errors->newsletter->any() ? old('email') : '' }}">
                    <button type="submit" class="btn btn-primary">{{ __('site.newsletter_button') }}</button>
                </div>
                @if (session('newsletter_status'))
                    <p class="newsletter-msg is-ok" role="status">{{ session('newsletter_status') }}</p>
                @elseif ($errors->newsletter->any())
                    <p class="newsletter-msg is-error" role="alert">{{ $errors->newsletter->first() }}</p>
                @endif
            </form>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>{{ str_replace(':year', date('Y'), setting('copyright', '© :year '.$siteName)) }}</span>
        <a class="to-top" href="#">{{ __('site.back_to_top') }} <span aria-hidden="true">↑</span></a>
    </div>
</footer>

<script src="{{ asset('js/site.js') }}?v={{ @filemtime(public_path('js/site.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
