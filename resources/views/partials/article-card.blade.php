<article class="card article-card">
    <a class="article-thumb" href="{{ $article->url() }}" tabindex="-1" aria-hidden="true">
        @if ($article->image)
            <img src="{{ $article->mediaUrl() }}" alt="" loading="lazy">
        @else
            <span class="thumb-placeholder">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/></svg>
            </span>
        @endif
    </a>
    <div class="article-card-body">
        @if ($article->type === 'news' && $article->published_at)
            <time class="card-date" datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->format('d/m/Y') }}</time>
        @endif
        <h3 class="card-title"><a href="{{ $article->url() }}">{{ $article->title }}</a></h3>
        @if (! empty($showExcerpt))
            <p class="card-text">{{ $article->excerptText(140) }}</p>
        @endif
        <a class="card-link" href="{{ $article->url() }}">
            {{ __('site.read_more') }}
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
    </div>
</article>
