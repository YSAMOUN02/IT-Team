{{-- A course in a grid: cover, title, summary, facts, price. The title's link is stretched over the whole card. --}}
<article class="card course-card">
    <div class="course-cover">
        @if ($course->image)
            <img src="{{ $course->mediaUrl() }}" alt="" loading="lazy">
        @else
            <span class="course-cover-empty" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/></svg>
            </span>
        @endif
        <span class="course-flags">
            @if ($course->isFree())
                <span class="course-flag is-free">{{ __('account.free') }}</span>
            @elseif ($course->bestDiscount())
                <span class="course-flag is-sale">−{{ $course->bestDiscount() }}%</span>
            @endif
            @if ($course->badge)
                <span class="course-flag course-flag-end">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <span @if (ctype_digit((string) $course->badge)) data-count="{{ $course->badge }}" @endif>{{ $course->badge }}</span>
                    <span class="sr-only">{{ __('site.learners') }}</span>
                </span>
            @endif
        </span>
    </div>
    <div class="course-card-body">
        @if ($course->icon)
            <img class="course-icon" src="{{ $course->mediaUrl('icon') }}" alt="" width="52" height="52" loading="lazy">
        @endif
        <h3 class="card-title"><a href="{{ $course->url() }}">{{ $course->title }}</a></h3>
        @if ($course->summary)
            <p class="card-text">{{ \Illuminate\Support\Str::limit($course->summary, 170) }}</p>
        @endif
        @if ($course->duration || $course->lessons_count)
            <ul class="course-card-meta">
                @if ($course->duration)
                    <li>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        {{ $course->duration }}
                    </li>
                @endif
                @if ($course->lessons_count)
                    <li>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/></svg>
                        {{ trans_choice('account.lessons_count', $course->lessons_count, ['count' => $course->lessons_count]) }}
                    </li>
                @endif
            </ul>
        @endif
        <div class="course-card-foot">
            @if ($course->isFree())
                <span class="price-tag is-free">{{ __('account.free') }}</span>
            @elseif ($course->plans->isNotEmpty())
                <span class="price-tag">{{ $course->formattedPrice() }}</span>
            @endif
            <span class="card-link" aria-hidden="true">
                {{ __('site.view_course') }}
                <svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </span>
        </div>
    </div>
</article>
