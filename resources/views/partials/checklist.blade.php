@if (! empty($items))
    <ul class="checklist">
        @foreach ($items as $item)
            <li>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                <span>{{ trim(preg_replace('/^[\s\x{200B}-\x{200D}\x{FEFF}]*(?:[-–—•·*]|✅|✔️?|☑️?)\s*/u', '', $item)) }}</span>
            </li>
        @endforeach
    </ul>
@endif
