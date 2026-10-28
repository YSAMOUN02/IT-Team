@extends('admin.layout')

@section('title', 'Testimonials')
@section('subtitle', 'Learner reviews shown on the home page.')
@section('crumbs')<span aria-hidden="true">/</span><span>Showcase</span><span aria-hidden="true">/</span><span>Testimonials</span>@endsection
@section('actions')
    <a class="btn btn-primary" href="{{ route('admin.testimonials.create') }}">@include('admin.partials.icon', ['name' => 'plus']) New testimonial</a>
@endsection

@section('content')
    <div class="card reveal">
        @if ($testimonials->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'quote'])</div>
                <h3>No testimonials yet</h3>
                <p>Add what learners say about your courses.</p>
                <a class="btn btn-primary" href="{{ route('admin.testimonials.create') }}">@include('admin.partials.icon', ['name' => 'plus']) New testimonial</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Person</th><th>Quote</th><th>Rating</th><th>Order</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($testimonials as $testimonial)
                        <tr>
                            <td>
                                <div class="row-title" style="min-width:180px">
                                    @if ($testimonial->photo)
                                        <img class="thumb round" src="{{ $testimonial->mediaUrl('photo') }}" alt="">
                                    @else
                                        <span class="avatar">{{ mb_substr($testimonial->name, 0, 1) }}</span>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.testimonials.edit', $testimonial) }}">{{ $testimonial->name }}</a>
                                        @if ($testimonial->role)<span class="row-sub">{{ $testimonial->role }}</span>@endif
                                    </div>
                                </div>
                            </td>
                            <td><span class="row-sub" style="max-width:420px">{{ $testimonial->quote }}</span></td>
                            <td class="num" aria-label="{{ $testimonial->rating }} out of 5">{{ str_repeat('★', $testimonial->rating) }}</td>
                            <td class="num">{{ $testimonial->sort_order }}</td>
                            <td><span class="status {{ $testimonial->is_published ? 'status-on' : 'status-off' }}">{{ $testimonial->is_published ? 'Live' : 'Hidden' }}</span></td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.testimonials.edit', $testimonial) }}">@include('admin.partials.icon', ['name' => 'edit']) Edit</a>
                                <form method="post" action="{{ route('admin.testimonials.destroy', $testimonial) }}" data-confirm="Delete the testimonial from {{ $testimonial->name }}?">
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
