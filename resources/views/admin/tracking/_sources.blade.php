{{-- Where the people came from: $sources (see AnalyticsController::breakdown). --}}
<section class="card">
    <div class="card-head"><div><h2>Where they come from</h2><p>People, in these dates{{ ! empty($sourcesFor) ? ' · '.$sourcesFor : '' }}</p></div></div>
    @if ($sources->isEmpty())
        <div class="card-body"><p class="track-empty">Nobody yet in these dates.</p></div>
    @else
        <ul class="breakdown">
            @foreach ($sources as $source)
                <li>
                    <span class="breakdown-name">{{ $source->name }}</span>
                    <span class="breakdown-value">{{ $source->visitors }} <small>{{ $source->share }}%</small></span>
                    <span class="bar-line"><span style="width: {{ $source->share }}%"></span></span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
