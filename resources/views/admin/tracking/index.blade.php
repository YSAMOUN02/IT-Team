@extends('admin.layout')

@php
    $fromValue = $from->format('Y-m-d');
    $toValue = $to->format('Y-m-d');
    // Links on this page keep the dates, tab and post type unless told otherwise (null removes one).
    $here = fn (array $change = []) => route('admin.tracking', array_filter(array_merge(
        ['tab' => $tab, 'from' => $fromValue, 'to' => $toValue, 'type' => $postType ?? null], $change
    ), fn ($value) => $value !== null));
    $time = fn ($s) => $s ? ($s >= 60 ? intdiv($s, 60).'m '.str_pad($s % 60, 2, '0', STR_PAD_LEFT).'s' : $s.'s') : '—';
    $periodText = $fromValue === $toValue ? $from->format('j M Y') : $from->format('j M Y').' – '.$to->format('j M Y');
    $itemId = $selected ?? null;
@endphp

@section('title', 'Tracking')
@section('subtitle', 'How each post, course and ad did from one date to another.')
@section('crumbs')<span aria-hidden="true">/</span><span>Overview</span><span aria-hidden="true">/</span><span>Tracking</span>@endsection

@section('content')
<div class="reveal">
    <section class="card range-card" aria-label="Dates">
        <form class="range-form" method="get" action="{{ route('admin.tracking') }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if ($postType ?? null)<input type="hidden" name="type" value="{{ $postType }}">@endif
            <label class="range-field"><span>From</span><input type="date" name="from" value="{{ $fromValue }}" max="{{ today()->format('Y-m-d') }}" required></label>
            <label class="range-field"><span>To</span><input type="date" name="to" value="{{ $toValue }}" max="{{ today()->format('Y-m-d') }}" required></label>
            <button class="btn btn-primary">@include('admin.partials.icon', ['name' => 'calendar']) Show</button>
        </form>
        <div class="range-presets" role="group" aria-label="Quick dates">
            @foreach ($presets as $presetLabel => [$presetFrom, $presetTo])
                <a href="{{ $here(['from' => $presetFrom, 'to' => $presetTo]) }}" @class(['on' => $presetFrom === $fromValue && $presetTo === $toValue])>{{ $presetLabel }}</a>
            @endforeach
        </div>
    </section>

    <div class="track-head">
        <nav class="segmented track-tabs" aria-label="What to track">
            @foreach ($tabs as $key => $tabLabel)
                <a href="{{ $here(['tab' => $key, 'item' => null, 'type' => null]) }}" @class(['on' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>{{ $tabLabel }}</a>
            @endforeach
        </nav>
        <p class="track-period"><b>{{ $periodText }}</b> · {{ $days }} {{ str('day')->plural($days) }}, compared with the {{ $days === 1 ? 'day' : $days.' days' }} before</p>
    </div>

    @include('admin.tracking._'.$tab)

    <p class="muted analytics-note">
        Counted on this website itself. Admins and search-engine bots are not counted, and no IP addresses are kept.
        @if ($tab === 'ads')
            A banner counts as viewed once half of it has been on screen, once per page opened. Banner views started when this page was added, so older dates can show clicks without views. Clicks on Google ads are only in your AdSense account.
        @elseif ($tab === 'courses')
            “New students” are accounts given the course in these dates, by payment or by an admin. Income comes from paid invoices; refunds are left out.
        @else
            “Readers” are different browsers; one person reading twice is 2 views and 1 reader.
        @endif
    </p>
</div>
@endsection
