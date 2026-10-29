{{-- Access cell for one enrollment: running (until / lifetime), ended, or turned off by an admin. --}}
@if ($enrollment->disabled_at)
    <span class="status status-blocked">Turned off</span>
    <span class="row-sub">since {{ $enrollment->disabled_at->format('d M Y') }} ·
        {{ ! $enrollment->inPeriod() ? 'ended '.$enrollment->expires_at?->format('d M Y') : ($enrollment->expires_at ? 'paid until '.$enrollment->expires_at->format('d M Y') : 'lifetime') }}</span>
@elseif (! $enrollment->isActive())
    <span class="status status-off">Ended {{ $enrollment->expires_at?->format('d M Y') }}</span>
@else
    <span class="status status-on">{{ $enrollment->expires_at ? 'Until '.$enrollment->expires_at->format('d M Y') : 'Lifetime' }}</span>
@endif
