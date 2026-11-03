{{-- Tracking › Courses: opened → clicked Buy / Enrol → became a student → income. Click a row to see one course on its own. --}}
@php
    $maxVisitors = max(1, $rows->max('visitors'));
@endphp
<section class="kpis kpis-mini track-kpis" aria-label="Summary" id="detail">
    @include('admin.tracking._kpi', ['kpiIcon' => 'eye', 'kpiLabel' => 'Opened the course', 'kpiValue' => number_format($totals->visitors), 'kpiChange' => $change['visitors'], 'kpiNote' => 'people · '.number_format($totals->views).' '.str('view')->plural($totals->views)])
    @include('admin.tracking._kpi', ['kpiIcon' => 'arrow', 'kpiLabel' => 'Clicked Buy / Enroll', 'kpiValue' => number_format($totals->clicked_buy), 'kpiNote' => $totals->visitors ? round($totals->clicked_buy * 100 / $totals->visitors).'% of those who opened' : 'people'])
    @include('admin.tracking._kpi', ['kpiIcon' => 'users', 'kpiLabel' => 'New students', 'kpiValue' => number_format($totals->students), 'kpiChange' => $change['students'], 'kpiNote' => 'given the course'])
    @include('admin.tracking._kpi', ['kpiIcon' => 'check', 'kpiLabel' => 'Income', 'kpiValue' => $totals->income, 'kpiNote' => $totals->invoices.' paid '.str('invoice')->plural($totals->invoices)])
</section>

<div class="charts">
    @include('admin.tracking._chart', ['chartTitle' => 'People who opened, per '.$per, 'chartNote' => $selected ? \Illuminate\Support\Str::limit($selected->title, 60) : 'All courses', 'chartData' => $chart, 'chartUnit' => 'people', 'chartTotal' => $totals->visitors])
    @include('admin.tracking._sources', ['sourcesFor' => $selected ? \Illuminate\Support\Str::limit($selected->title, 40) : null])
</div>

<section class="card track-table">
    <div class="card-head">
        <div>
            <h2>Courses</h2>
            <p>@if ($selected)<a href="{{ $here(['item' => null]) }}">← Show all courses</a>@else Most opened first. Click a course to see it on its own.@endif</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Course</th><th class="num">Opened</th><th class="num hide-sm">Clicked Buy</th><th class="num">New<span class="hide-sm-inline"> students</span></th><th class="num hide-sm">Income</th><th class="num hide-sm">Studying</th><th class="num hide-sm">Finished</th></tr></thead>
            <tbody>
            @foreach ($rows as $row)
                @php
                    $isSelected = $selected?->id === $row->course->id;
                @endphp
                <tr @class(['is-selected' => $isSelected, 'is-quiet' => ! $row->visitors && ! $row->students])>
                    <td>
                        <div class="row-title">
                            @if ($row->course->image)<img class="track-thumb" src="{{ $row->course->mediaUrl() }}" alt="" loading="lazy">@endif
                            <div>
                                <a href="{{ $here(['item' => $isSelected ? null : $row->course->id]) }}#detail">{{ $row->course->title }}</a>
                                @if ($row->course->is_free)<span class="status status-on">Free</span>@endif
                                <span class="row-sub">
                                    <span class="show-sm">{{ $row->clicked_buy }} clicked Buy{{ $row->income !== '—' ? ' · '.$row->income : '' }} · </span>
                                    <a href="{{ $row->course->url() }}" target="_blank" rel="noopener">Open ↗</a>
                                </span>
                                <span class="bar-line"><span style="width: {{ round($row->visitors * 100 / $maxVisitors) }}%"></span></span>
                            </div>
                        </div>
                    </td>
                    <td class="num"><b>{{ number_format($row->visitors) }}</b><span class="row-sub hide-sm-inline">{{ number_format($row->views) }} {{ str('view')->plural($row->views) }}</span></td>
                    <td class="num hide-sm">{{ number_format($row->clicked_buy) }}@if ($row->visitors)<span class="row-sub">{{ round($row->clicked_buy * 100 / $row->visitors) }}% of opened</span>@endif</td>
                    <td class="num">{{ number_format($row->students) }}</td>
                    <td class="num hide-sm">{{ $row->income }}</td>
                    <td class="num hide-sm">{{ number_format($row->studying) }}</td>
                    <td class="num hide-sm">{{ number_format($row->finished) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
