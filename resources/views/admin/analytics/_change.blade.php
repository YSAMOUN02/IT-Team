{{-- "+12%" / "−5%" against the previous period of the same length; nothing when there is nothing to compare. --}}
@if ($value !== null)
    <span @class(['up' => $value > 0, 'down' => $value < 0])>{{ $value > 0 ? '+' : ($value < 0 ? '−' : '±') }}{{ abs($value) }}%</span>
@endif
