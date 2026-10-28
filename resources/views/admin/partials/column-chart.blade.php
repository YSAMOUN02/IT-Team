{{--
    Single-series column chart (server-rendered SVG, hover/focus tooltips from admin.js).
    $data = [['label' => 'Mon 3 Oct', 'tick' => '3 Oct'|null, 'value' => 2], ...]
    $unit = noun for the tooltip/table, e.g. "messages"
--}}
@php
    $W = 560; $H = 200;
    $padL = 30; $padR = 6; $padT = 20; $padB = 24;
    $plotW = $W - $padL - $padR;
    $plotH = $H - $padT - $padB;
    $base = $padT + $plotH;
    $values = array_column($data, 'value');
    $max = max($values ?: [0]);

    // Round the top of the scale to a clean number (1, 2, 5 x 10^n), at least 4.
    $niceMax = 4;
    if ($max > 4) {
        $mag = 10 ** floor(log10($max));
        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($step * $mag >= $max) { $niceMax = $step * $mag; break; }
        }
    }
    $ticks = fmod($niceMax / 2, 1) == 0 ? [0, $niceMax / 2, $niceMax] : [0, $niceMax];

    $n = max(count($data), 1);
    $slot = $plotW / $n;
    $barW = min(24, $slot * 0.62);
    $peakIndex = $max > 0 ? array_search($max, $values) : null;
@endphp
<div class="chart" data-chart data-height="{{ $H }}">
    <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ $title ?? 'Chart' }}">
        <g class="grid">
            @foreach ($ticks as $t)
                @php
                    $y = $base - ($t / $niceMax) * $plotH;
                @endphp
                @if ($t > 0)<line x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y }}" y2="{{ $y }}"/>@endif
                <text class="tick" x="{{ $padL - 8 }}" y="{{ $y + 3.5 }}" text-anchor="end">{{ $t }}</text>
            @endforeach
        </g>
        <line class="baseline" x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $base }}" y2="{{ $base }}"/>

        @foreach ($data as $i => $point)
            @php
                $x = $padL + $i * $slot + ($slot - $barW) / 2;
                $h = $niceMax ? ($point['value'] / $niceMax) * $plotH : 0;
                $top = $base - $h;
                $r = min(4, $barW / 2, $h);
            @endphp
            <rect class="hit" x="{{ $padL + $i * $slot }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" tabindex="0"
                  aria-label="{{ $point['label'] }}: {{ $point['value'] }} {{ $unit }}"
                  data-label="{{ $point['label'] }}" data-value="{{ $point['value'] }} {{ $unit }}" data-top="{{ $top }}"/>
            @if ($h > 0)
                <path class="bar" pointer-events="none" d="M{{ $x }},{{ $base }} V{{ $top + $r }} Q{{ $x }},{{ $top }} {{ $x + $r }},{{ $top }} H{{ $x + $barW - $r }} Q{{ $x + $barW }},{{ $top }} {{ $x + $barW }},{{ $top + $r }} V{{ $base }} Z"/>
            @else
                <path class="bar" pointer-events="none" d=""/>
            @endif
            @if ($i === $peakIndex)
                <text class="peak" x="{{ $x + $barW / 2 }}" y="{{ $top - 6 }}" text-anchor="middle">{{ $point['value'] }}</text>
            @endif
            @if ($point['tick'])
                <text class="tick" x="{{ $x + $barW / 2 }}" y="{{ $H - 6 }}" text-anchor="middle">{{ $point['tick'] }}</text>
            @endif
        @endforeach
    </svg>
    <div class="tooltip" role="status" hidden><strong></strong><span></span></div>

    <details class="data-table">
        <summary>View as table</summary>
        <table>
            <thead><tr><th>{{ $periodLabel ?? 'Period' }}</th><th>{{ ucfirst($unit) }}</th></tr></thead>
            <tbody>
                @foreach (array_reverse($data) as $point)
                    <tr><td>{{ $point['label'] }}</td><td>{{ $point['value'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>
</div>
