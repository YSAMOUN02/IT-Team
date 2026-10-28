{{-- "What the course covers": grouped cards when the list has headings, otherwise a plain checklist. --}}
@php
    $groups = $course->featureGroups();
    $hasHeadings = collect($groups)->contains(fn ($g) => $g['title'] !== null);
@endphp
@if ($groups)
    @if ($hasHeadings)
        <div class="feature-groups">
            @foreach ($groups as $group)
                <div @class(['feature-group', 'is-single' => ! $group['items']])>
                    @if ($group['title'] !== null)
                        <h3>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                            {{ $group['title'] }}
                        </h3>
                    @endif
                    @if ($group['items'])
                        <ul>
                            @foreach ($group['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        @include('partials.checklist', ['items' => collect($groups)->pluck('items')->flatten()->all()])
    @endif
@endif
