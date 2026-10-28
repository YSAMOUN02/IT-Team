@php($siteName = setting('site_name', config('app.name')))
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Sign in · {{ $siteName }} Admin</title>
    @if (setting('favicon'))
        <link rel="icon" href="{{ media_url(setting('favicon')) }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&family=Kantumruy+Pro:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="auth">
    <aside class="auth-art">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark">
                @if (setting('favicon'))
                    <img src="{{ media_url(setting('favicon')) }}" alt="">
                @else
                    @include('admin.partials.icon', ['name' => 'grid'])
                @endif
            </span>
            <span><span class="brand-name">{{ $siteName }}</span><span class="brand-sub">Admin workspace</span></span>
        </a>

        <div>
            <h1>Run the website like a <span>well-kept workbook</span>.</h1>
            <p>Courses, lessons, news and messages, all in one place.</p>
        </div>

        <div class="sheet" aria-hidden="true">
            <div class="h"></div><div class="h">A</div><div class="h">B</div><div class="h">C</div>
            <div class="h">1</div><div>Courses</div><div>Lessons</div><div>News</div>
            <div class="h">2</div><div>{{ \App\Models\Course::count() }}</div><div>{{ \App\Models\Article::ofType('tutorial')->count() }}</div><div class="sel">=TODAY()</div>
        </div>
    </aside>

    <main class="auth-form">
        <form class="auth-card fields" method="post" action="{{ route('admin.login.store') }}">
            @csrf
            <div>
                <h2>Welcome back</h2>
                <p>Sign in to manage {{ $siteName }}.</p>
            </div>
            @if ($errors->any())
                <div class="alert alert-error" role="alert" style="margin:0">@include('admin.partials.icon', ['name' => 'alert']) {{ $errors->first() }}</div>
            @endif
            <div class="field">
                <label class="field-label" for="login"><span>Username or email</span></label>
                <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
            </div>
            <div class="field">
                <label class="field-label" for="password"><span>Password</span></label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button type="submit" class="btn btn-primary btn-block">Sign in @include('admin.partials.icon', ['name' => 'arrow'])</button>
            <a class="auth-back" href="{{ route('home') }}">← Back to the website</a>
        </form>
    </main>
</div>
</body>
</html>
