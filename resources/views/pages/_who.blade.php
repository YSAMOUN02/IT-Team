{{--
    About page, "Who we are": photo with name card, education & certificates as
    small cards, years of experience with area tags, a short "why" story and the
    course strengths. Every part comes from Admin > Pages > About > Who we are.
    List lines can be "Title៖ detail" (or "Title: detail").
--}}
@php
    $split = fn (string $line) => array_pad(preg_split('/\s*[៖:]\s*/u', trim($line), 2), 2, null);
    $credentials = (array) $page->field('who.credentials', []);
    $areas = (array) $page->field('who.experience', []);
    $offers = (array) $page->field('who.offers', []);
    $icons = [
        'study' => '<path d="M2 9.5 12 5l10 4.5L12 14z"/><path d="M6 11.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-4.5"/>',
        'cert' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-2.5 5 2.5-1.5-7"/>',
        'work' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12.5h18"/>',
    ];
    $iconFor = fn (string $line) => match (true) {
        (bool) preg_match('/បរិញ្ញាបត្រ|អនុបណ្ឌិត|បណ្ឌិត|សាកលវិទ្យាល័យ|Bachelor|Master|Degree|University/iu', $line) => $icons['study'],
        (bool) preg_match('/Certif|វិញ្ញាបនបត្រ/iu', $line) => $icons['cert'],
        default => $icons['work'],
    };
    $offerIcons = [
        '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.2"/>',
        '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        '<path d="M9 4h6l1 2h3v14H5V6h3z"/><path d="m9 13 2 2 4-4"/>',
    ];
@endphp

<section class="section who-section">
    <div class="container">
        <div class="who">
            @if ($page->field('who.image'))
                <figure class="who-photo">
                    <img src="{{ media_url($page->field('who.image')) }}" alt="{{ $page->field('who.name') }}" loading="lazy">
                    @if ($page->field('who.name'))
                        <figcaption class="who-badge">
                            <strong>{{ $page->field('who.name') }}</strong>
                            @if ($page->field('who.role'))<span>{{ $page->field('who.role') }}</span>@endif
                        </figcaption>
                    @endif
                </figure>
            @endif

            <div class="who-body">
                <h2>{{ $page->field('who.title') }}</h2>

                @if ($credentials)
                    <h3 class="who-sub">{{ __('site.about_credentials') }}</h3>
                    <ul class="cred-grid">
                        @foreach ($credentials as $line)
                            @php([$title, $detail] = $split($line))
                            <li>
                                <span class="cred-icon" aria-hidden="true"><svg viewBox="0 0 24 24">{!! $iconFor($line) !!}</svg></span>
                                <span><strong>{{ $title }}</strong>@if ($detail)<small>{{ $detail }}</small>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($page->field('who.experience_title') || $areas)
                    <div class="who-exp">
                        @if ($page->field('who.experience_years'))
                            <span class="who-exp-num">{{ $page->field('who.experience_years') }}<small>{{ __('site.about_years') }}</small></span>
                        @endif
                        <div>
                            @if ($page->field('who.experience_title'))<strong>{{ $page->field('who.experience_title') }}</strong>@endif
                            @if ($areas)
                                <div class="who-tags">
                                    @foreach ($areas as $area)<span>{{ $area }}</span>@endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($page->field('who.story'))
                    <blockquote class="who-story">
                        <strong>{{ __('site.about_why') }}</strong>
                        <p>{{ $page->field('who.story') }}</p>
                    </blockquote>
                @endif

                @if ($page->field('who.text'))
                    <div class="prose who-extra">{!! rich_text($page->field('who.text')) !!}</div>
                @endif
            </div>
        </div>

        @if ($offers)
            <div class="who-offers">
                @if ($page->field('who.offers_title'))
                    <h3>{{ $page->field('who.offers_title') }}</h3>
                @endif
                <ul class="offer-grid">
                    @foreach ($offers as $i => $line)
                        @php([$title, $detail] = $split($line))
                        <li>
                            <span class="offer-icon" aria-hidden="true"><svg viewBox="0 0 24 24">{!! $offerIcons[$i % 3] !!}</svg></span>
                            <strong>{{ $title }}</strong>
                            @if ($detail)<p>{{ $detail }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
