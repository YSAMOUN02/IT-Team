@extends('admin.layout')

@section('title', 'Pages')
@section('subtitle', 'The fixed pages of the website. Each one is split into editable sections.')
@section('crumbs')<span aria-hidden="true">/</span><span>Content</span><span aria-hidden="true">/</span><span>Pages</span>@endsection

@section('content')
    <div class="card reveal">
        @if ($pages->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'pages'])</div>
                <h3>No pages yet</h3>
                <p>Run <code>php artisan db:seed</code> or <code>php artisan wp:import</code> to create them.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Page</th><th>Layout</th><th>Sections</th><th>Updated</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($pages as $page)
                        @php($url = $page->slug === 'home' ? url('/') : url($page->slug))
                        <tr>
                            <td>
                                <div class="row-title">
                                    <span class="thumb">@include('admin.partials.icon', ['name' => $page->slug === 'home' ? 'home' : 'pages'])</span>
                                    <div>
                                        <a href="{{ route('admin.pages.edit', $page) }}">{{ $page->title }}</a>
                                        <span class="row-sub mono">/{{ $page->slug === 'home' ? '' : $page->slug }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="muted">{{ $page->templateDefinition()['label'] }}</td>
                            <td class="num">{{ count($page->templateDefinition()['groups']) }}</td>
                            <td class="num">{{ $page->updated_at?->diffForHumans(short: true) }}</td>
                            <td><span class="status {{ $page->is_published ? 'status-on' : 'status-off' }}">{{ $page->is_published ? 'Live' : 'Hidden' }}</span></td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm btn-icon" href="{{ $url }}" target="_blank" rel="noopener" title="View on website" aria-label="View on website">@include('admin.partials.icon', ['name' => 'eye'])</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.pages.edit', $page) }}">@include('admin.partials.icon', ['name' => 'edit']) Edit</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
