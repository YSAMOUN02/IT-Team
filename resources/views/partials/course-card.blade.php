<article class="card course-card">
    <div class="course-card-top">
        @if ($course->icon)
            <img class="course-icon" src="{{ $course->mediaUrl('icon') }}" alt="" width="44" height="44" loading="lazy">
        @else
            <span class="course-icon course-icon-default" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/></svg>
            </span>
        @endif
        @if ($course->badge)
            <span class="course-badge" title="{{ __('site.learners') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                <span @if (ctype_digit((string) $course->badge)) data-count="{{ $course->badge }}" @endif>{{ $course->badge }}</span>
            </span>
        @endif
    </div>
    <h3 class="card-title"><a href="{{ $course->url() }}">{{ $course->title }}</a></h3>
    @if ($course->summary)
        <p class="card-text">{{ \Illuminate\Support\Str::limit($course->summary, 170) }}</p>
    @endif
    @if ($course->duration || $course->isFree() || $course->plans->isNotEmpty())
        <div class="course-meta">
            @if ($course->duration)
                <span class="course-duration">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    {{ $course->duration }}
                </span>
            @endif
            @if ($course->isFree())
                <span class="price-tag is-free">{{ __('account.free') }}</span>
            @elseif ($course->plans->isNotEmpty())
                <span class="price-tag">
                    @if ($course->bestDiscount())<b class="disc">−{{ $course->bestDiscount() }}%</b>@endif
                    {{ $course->formattedPrice() }}
                </span>
            @endif
        </div>
    @endif
    <a class="card-link" href="{{ $course->url() }}">
        {{ __('site.view_course') }}
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
</article>
