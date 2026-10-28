@extends('admin.layout')

@section('title', 'Partners')
@section('subtitle', 'Organisation logos shown on the home page.')
@section('crumbs')<span aria-hidden="true">/</span><span>Showcase</span><span aria-hidden="true">/</span><span>Partners</span>@endsection
@section('actions')
    <a class="btn btn-primary" href="{{ route('admin.partners.create') }}">@include('admin.partials.icon', ['name' => 'plus']) New partner</a>
@endsection

@section('content')
    <div class="card reveal">
        @if ($partners->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'partners'])</div>
                <h3>No partners yet</h3>
                <p>Add the organisations you have trained.</p>
                <a class="btn btn-primary" href="{{ route('admin.partners.create') }}">@include('admin.partials.icon', ['name' => 'plus']) New partner</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Partner</th><th>Website</th><th>Order</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($partners as $partner)
                        <tr>
                            <td>
                                <div class="row-title" style="min-width:180px">
                                    <img class="thumb contain" src="{{ $partner->mediaUrl('logo') }}" alt="">
                                    <a href="{{ route('admin.partners.edit', $partner) }}">{{ $partner->name }}</a>
                                </div>
                            </td>
                            <td>@if ($partner->url)<a class="mono" href="{{ $partner->url }}" target="_blank" rel="noopener">{{ preg_replace('~^https?://(www\.)?~', '', rtrim($partner->url, '/')) }}</a>@endif</td>
                            <td class="num">{{ $partner->sort_order }}</td>
                            <td><span class="status {{ $partner->is_published ? 'status-on' : 'status-off' }}">{{ $partner->is_published ? 'Shown' : 'Hidden' }}</span></td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.partners.edit', $partner) }}">@include('admin.partials.icon', ['name' => 'edit']) Edit</a>
                                <form method="post" action="{{ route('admin.partners.destroy', $partner) }}" data-confirm="Remove {{ $partner->name }} from the partners?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm btn-icon" title="Delete" aria-label="Delete">@include('admin.partials.icon', ['name' => 'trash'])</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
