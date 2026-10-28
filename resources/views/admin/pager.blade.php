@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">
        @if (! $paginator->onFirstPage())
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
        @endif
        @foreach ($elements as $element)
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @elseif (is_string($element))
                <span class="gap">{{ $element }}</span>
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
        @endif
    </nav>
@endif
