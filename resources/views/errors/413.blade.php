{{-- PHP threw a form away because its files added up to more than post_max_size. The session never started,
     so this page stands alone (no site layout). admin.js normally stops this before it happens. --}}
@php($limit = is_finite($bytes = \App\Support\Media::postMaxBytes()) ? round($bytes / 1048576).' MB' : null)
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Files too big · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>
    <section class="section coming-soon">
        <div class="container narrow">
            <p class="error-code">413</p>
            <h1>The files were too big to send</h1>
            <p class="lead">Nothing was saved. This server takes {{ $limit ?? 'only so much' }} per save, and the files you picked add up to more.
                Go back and pick a smaller file, or save with fewer files at a time.</p>
            <p class="muted">For long lesson videos, a YouTube or Vimeo link works without uploading.</p>
            <div class="btn-row btn-row-center">
                <a class="btn btn-primary" href="javascript:history.back()">← Go back</a>
            </div>
        </div>
    </section>
</body>
</html>
