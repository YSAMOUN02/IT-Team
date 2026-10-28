@if (isset($item['children']))
    @php($open = collect($item['children'])->contains(fn ($c) => request()->routeIs($c['active'] ?? $c['route'])))
    <li class="has-children">
        <button type="button" @class(['nav-link', 'active' => $open]) aria-expanded="false" data-dropdown>
            {{ $item['label'] }}
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <ul class="dropdown">
            @foreach ($item['children'] as $child)
                <li><a href="{{ route($child['route']) }}" @class(['active' => request()->routeIs($child['active'] ?? $child['route'])])>{{ $child['label'] }}</a></li>
            @endforeach
        </ul>
    </li>
@else
    <li><a href="{{ route($item['route']) }}" @class(['nav-link', 'active' => request()->routeIs($item['active'] ?? $item['route'])])>{{ $item['label'] }}</a></li>
@endif
