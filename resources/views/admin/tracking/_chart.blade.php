{{-- A column chart card, or a plain line when there is nothing to draw: $chartTitle, $chartNote, $chartData, $chartUnit, $chartTotal. --}}
<section class="card">
    <div class="card-head"><div><h2>{{ $chartTitle }}</h2><p>{{ $chartNote }}</p></div></div>
    <div class="card-body">
        @if (collect($chartData)->sum('value') > 0)
            <div class="chart-total"><b>{{ number_format($chartTotal) }}</b><span>{{ $chartUnit }}</span></div>
            @include('admin.partials.column-chart', ['data' => $chartData, 'unit' => $chartUnit, 'title' => $chartTitle, 'periodLabel' => ucfirst($per)])
        @else
            <p class="track-empty">No {{ $chartUnit }} between these dates.</p>
        @endif
    </div>
</section>
