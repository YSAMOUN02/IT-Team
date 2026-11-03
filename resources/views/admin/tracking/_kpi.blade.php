{{-- One number at the top of Tracking: $kpiIcon, $kpiLabel, $kpiValue, $kpiNote, optional $kpiChange (percent against the period before). --}}
<div class="card kpi">
    <span class="kpi-icon">@include('admin.partials.icon', ['name' => $kpiIcon])</span>
    <div class="kpi-label">{{ $kpiLabel }}</div>
    <div class="kpi-value">{{ $kpiValue }}</div>
    <div class="kpi-note">
        @isset($kpiChange)@include('admin.analytics._change', ['value' => $kpiChange])@endisset
        {{ $kpiNote }}
    </div>
</div>
