{{-- Tracking › Ads: per spot, how often the banner was on screen and how often it was clicked. Click a row to see one spot on its own. --}}
@php
    $maxViews = max(1, $rows->max('views'));
    $rateText = fn (?float $rate) => $rate === null ? '—' : rtrim(rtrim(number_format($rate, 1), '0'), '.').'%';
@endphp
<section class="kpis kpis-mini track-kpis" aria-label="Summary" id="detail">
    @include('admin.tracking._kpi', ['kpiIcon' => 'eye', 'kpiLabel' => 'Banner views', 'kpiValue' => number_format($totals->views), 'kpiChange' => $change['views'], 'kpiNote' => 'times on screen'])
    @include('admin.tracking._kpi', ['kpiIcon' => 'users', 'kpiLabel' => 'People who saw', 'kpiValue' => number_format($totals->visitors), 'kpiNote' => 'different people'])
    @include('admin.tracking._kpi', ['kpiIcon' => 'link', 'kpiLabel' => 'Clicks', 'kpiValue' => number_format($totals->clicks), 'kpiChange' => $change['clicks'], 'kpiNote' => 'on your own banners'])
    @include('admin.tracking._kpi', ['kpiIcon' => 'ads', 'kpiLabel' => 'Click rate', 'kpiValue' => $rateText($totals->rate), 'kpiNote' => 'clicks per 100 views'])
</section>

<div class="charts">
    @include('admin.tracking._chart', ['chartTitle' => 'Banner views per '.$per, 'chartNote' => $selectedLabel ?? 'All spots', 'chartData' => $chart, 'chartUnit' => 'views', 'chartTotal' => $totals->views])
    @include('admin.tracking._chart', ['chartTitle' => 'Clicks per '.$per, 'chartNote' => $selectedLabel ?? 'All spots', 'chartData' => $chartClicks, 'chartUnit' => 'clicks', 'chartTotal' => $totals->clicks])
</div>

<section class="card track-table">
    <div class="card-head">
        <div>
            <h2>Ad spots</h2>
            <p>@if ($selected)<a href="{{ $here(['item' => null]) }}">← Show all spots</a>@else Click a spot to see it on its own.@endif</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.ads.edit') }}">@include('admin.partials.icon', ['name' => 'edit']) Manage ads</a>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Spot</th><th class="num">Views</th><th class="num hide-sm">People</th><th class="num">Clicks</th><th class="num hide-sm">Click rate</th></tr></thead>
            <tbody>
            @foreach ($rows as $row)
                @php
                    $isSelected = $selected === $row->key;
                    $google = $row->kind === 'google';
                @endphp
                <tr @class(['is-selected' => $isSelected, 'is-quiet' => ! $row->views && ! $row->clicks])>
                    <td>
                        <div class="row-title">
                            @if ($row->banner)<img class="track-thumb track-thumb-wide" src="{{ $row->banner['image'] }}" alt="" loading="lazy">@endif
                            <div>
                                <a href="{{ $here(['item' => $isSelected ? null : $row->key]) }}#detail">{{ $row->label }}</a>
                                @if ($row->same_as_header)
                                    <span class="status status-sched" title="The same picture shows once per page, in the highest spot. Use a different picture to show this spot as well.">Same picture as header</span>
                                @elseif ($row->state === 'live')
                                    <span class="status status-on">{{ $google ? 'Google ad' : 'Your banner' }}</span>
                                @elseif ($row->state === 'placeholder')
                                    <span class="status status-off">No banner yet</span>
                                @else
                                    <span class="status status-off">Off</span>
                                @endif
                                <span class="row-sub">{{ $row->where }}</span>
                                <span class="bar-line"><span style="width: {{ round($row->views * 100 / $maxViews) }}%"></span></span>
                            </div>
                        </div>
                    </td>
                    <td class="num"><b>{{ number_format($row->views) }}</b></td>
                    <td class="num hide-sm">{{ number_format($row->visitors) }}</td>
                    <td class="num">@if ($google)<span title="Google counts clicks on its ads in your AdSense account">—</span>@else{{ number_format($row->clicks) }}@endif</td>
                    <td class="num hide-sm">{{ $google ? '—' : $rateText($row->rate) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
