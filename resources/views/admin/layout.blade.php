@php
    $siteName = setting('site_name', config('app.name'));
    $user = auth()->user();
    $articleType = isset($article) ? $article->type : request('type', 'tutorial');
    $onArticles = request()->routeIs('admin.articles.*');
    $groups = [
        ['A', 'Overview', [
            ['Dashboard', route('admin.dashboard'), request()->routeIs('admin.dashboard'), 'home', null],
            ['Visitors', route('admin.analytics'), request()->routeIs('admin.analytics'), 'chart', ['count', $navCounts['online']]],
            ['Messages', route('admin.inquiries.index'), request()->routeIs('admin.inquiries.*'), 'inbox', ['badge', $navCounts['inquiries']]],
            ['Subscribers', route('admin.subscribers.index'), request()->routeIs('admin.subscribers.*'), 'mail', ['count', $navCounts['subscribers']]],
        ]],
        ['B', 'Content', [
            ['Pages', route('admin.pages.index'), request()->routeIs('admin.pages.*'), 'pages', null],
            ['Courses', route('admin.courses.index'), request()->routeIs('admin.courses.*', 'admin.lessons.*', 'admin.quizzes.*', 'admin.questions.*'), 'courses', ['count', $navCounts['courses']]],
            ['Tutorials', route('admin.articles.index', ['type' => 'tutorial']), $onArticles && $articleType === 'tutorial', 'tutorials', ['count', $navCounts['tutorial']]],
            ['News', route('admin.articles.index', ['type' => 'news']), $onArticles && $articleType === 'news', 'news', ['count', $navCounts['news']]],
        ]],
        ['C', 'Sales', [
            ['Orders', route('admin.orders.index'), request()->routeIs('admin.orders.*'), 'courses', $navCounts['orders_review'] ? ['badge', $navCounts['orders_review']] : ['count', $navCounts['orders_paid']]],
            ['Students', route('admin.students.index'), request()->routeIs('admin.students.*'), 'users', ['count', $navCounts['students']]],
            ['Exam results', route('admin.results.index'), request()->routeIs('admin.results.*'), 'check', null],
            ['Re-exam requests', route('admin.retake-requests.index'), request()->routeIs('admin.retake-requests.*'), 'clock', ['badge', $navCounts['retakes']]],
        ]],
        ['D', 'Showcase', [
            ['Testimonials', route('admin.testimonials.index'), request()->routeIs('admin.testimonials.*'), 'quote', ['count', $navCounts['testimonials']]],
            ['Partners', route('admin.partners.index'), request()->routeIs('admin.partners.*'), 'partners', ['count', $navCounts['partners']]],
        ]],
        ['E', 'System', [
            ['Users', route('admin.users.index'), request()->routeIs('admin.users.*'), 'users', null],
            ['Ads', route('admin.ads.edit'), request()->routeIs('admin.ads.*'), 'ads', null],
            ['Settings', route('admin.settings.edit'), request()->routeIs('admin.settings.*'), 'settings', null],
        ]],
    ];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- PHP's post_max_size: one save with more files than this is thrown away whole (admin.js checks first). 0 = no limit. --}}
    <meta name="post-max-bytes" content="{{ is_finite($postMax = \App\Support\Media::postMaxBytes()) ? (int) $postMax : 0 }}">
    <title>@yield('title', 'Dashboard') · {{ $siteName }} Admin</title>
    @if (setting('favicon'))
        <link rel="icon" href="{{ media_url(setting('favicon')) }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&family=Kantumruy+Pro:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar" data-sidebar>
        <a class="brand" href="{{ route('admin.dashboard') }}">
            <span class="brand-mark">
                @if (setting('favicon'))
                    <img src="{{ media_url(setting('favicon')) }}" alt="">
                @else
                    @include('admin.partials.icon', ['name' => 'grid'])
                @endif
            </span>
            <span>
                <span class="brand-name">{{ $siteName }}</span>
                <span class="brand-sub">Admin workspace</span>
            </span>
        </a>

        <nav class="side-nav" aria-label="Admin">
            @foreach ($groups as [$letter, $label, $links])
                <div class="nav-group">
                    <div class="nav-group-label"><b>{{ $letter }}</b>{{ $label }}</div>
                    @foreach ($links as [$text, $url, $active, $icon, $extra])
                        <a href="{{ $url }}" @class(['active' => $active]) @if ($active) aria-current="page" @endif>
                            @include('admin.partials.icon', ['name' => $icon])
                            <span>{{ $text }}</span>
                            @if ($extra && $extra[0] === 'badge' && $extra[1])
                                <b class="nav-badge" title="Unread">{{ $extra[1] }}</b>
                            @elseif ($extra && $extra[0] === 'count')
                                <span class="nav-count">{{ $extra[1] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="side-foot">
            <a class="side-site" href="{{ route('home') }}" target="_blank" rel="noopener">
                @include('admin.partials.icon', ['name' => 'eye'])
                <span>View website</span>
                @include('admin.partials.icon', ['name' => 'external'])
            </a>
        </div>
    </aside>
    <div class="scrim" data-scrim hidden></div>

    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn menu-btn" data-sidebar-toggle aria-label="Open menu" aria-controls="sidebar">
                @include('admin.partials.icon', ['name' => 'menu'])
            </button>
            <form class="global-search" action="{{ route('admin.search') }}" method="get" role="search">
                @include('admin.partials.icon', ['name' => 'search'])
                <label class="sr-only" for="admin-search">Search content</label>
                <input id="admin-search" type="search" name="q" value="{{ request()->routeIs('admin.search') ? request('q') : '' }}" placeholder="Search courses, lessons, news, messages…" autocomplete="off" data-search>
                <kbd>/</kbd>
            </form>
            <div class="topbar-right">
                <a class="icon-btn" href="{{ route('home') }}" target="_blank" rel="noopener" title="View website" aria-label="View website">
                    @include('admin.partials.icon', ['name' => 'external'])
                </a>
                <details class="user-menu">
                    <summary>
                        <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="user-meta">
                            <span class="user-name">{{ $user->name }}</span><br>
                            <span class="user-role">{{ $user->email }}</span>
                        </span>
                    </summary>
                    <div class="menu-pop">
                        <a href="{{ route('admin.users.edit', $user) }}">@include('admin.partials.icon', ['name' => 'user']) My account</a>
                        <a href="{{ route('admin.settings.edit') }}">@include('admin.partials.icon', ['name' => 'settings']) Site settings</a>
                        <hr>
                        <form method="post" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="danger">@include('admin.partials.icon', ['name' => 'logout']) Log out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="page" id="main">
            @unless (View::hasSection('hide_head'))
                <div class="page-head">
                    <div>
                        <nav class="crumbs" aria-label="Breadcrumb">
                            <a href="{{ route('admin.dashboard') }}">Admin</a>
                            @hasSection('crumbs')
                                @yield('crumbs')
                            @endif
                        </nav>
                        <h1 class="page-title">@yield('title', 'Dashboard')</h1>
                        @hasSection('subtitle')
                            <p class="page-sub">@yield('subtitle')</p>
                        @endif
                    </div>
                    <div class="page-actions">@yield('actions')</div>
                </div>
            @endunless

            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    @include('admin.partials.icon', ['name' => 'alert'])
                    <div>
                        <strong>Please check the form.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<div class="toasts" aria-live="polite">
    @if (session('status'))
        <div class="toast" data-toast>
            <span class="ok">@include('admin.partials.icon', ['name' => 'check'])</span>
            <span>{{ session('status') }}</span>
            <button type="button" aria-label="Dismiss" data-toast-close>&times;</button>
        </div>
    @endif
</div>

<dialog class="confirm" data-confirm-dialog>
    <form method="dialog">
        <div class="confirm-body">
            <span class="confirm-icon">@include('admin.partials.icon', ['name' => 'trash'])</span>
            <div>
                <h2>Are you sure?</h2>
                <p data-confirm-text>This cannot be undone.</p>
            </div>
        </div>
        <div class="confirm-actions">
            <button class="btn btn-ghost" value="cancel">Cancel</button>
            <button class="btn btn-danger-solid" value="ok">Delete</button>
        </div>
    </form>
</dialog>

<script src="{{ asset('js/admin.js') }}?v={{ @filemtime(public_path('js/admin.js')) }}"></script>
@stack('scripts')
</body>
</html>
