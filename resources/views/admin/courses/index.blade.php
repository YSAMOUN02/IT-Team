@extends('admin.layout')

@section('title', 'Courses')
@section('subtitle', 'Training courses shown on the Courses page and the home page.')
@section('crumbs')<span aria-hidden="true">/</span><span>Content</span><span aria-hidden="true">/</span><span>Courses</span>@endsection
@section('actions')
    <a class="btn btn-ghost" href="{{ route('courses.index') }}" target="_blank" rel="noopener">@include('admin.partials.icon', ['name' => 'external']) View page</a>
    <a class="btn btn-primary" href="{{ route('admin.courses.create') }}">@include('admin.partials.icon', ['name' => 'plus']) New course</a>
@endsection

@section('content')
    <div class="card reveal">
        @if ($courses->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'courses'])</div>
                <h3>No courses yet</h3>
                <p>Add your first course to show it on the website.</p>
                <a class="btn btn-primary" href="{{ route('admin.courses.create') }}">@include('admin.partials.icon', ['name' => 'plus']) New course</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Course</th><th>Online price</th><th>Content</th><th>Students</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($courses as $course)
                        <tr>
                            <td>
                                <div class="row-title">
                                    @if ($course->image || $course->icon)
                                        <img class="thumb" src="{{ $course->mediaUrl() ?? $course->mediaUrl('icon') }}" alt="">
                                    @else
                                        <span class="thumb">@include('admin.partials.icon', ['name' => 'courses'])</span>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.courses.edit', $course) }}">{{ $course->title }}</a>
                                        <span class="row-sub">/courses/{{ $course->slug }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="num">
                                @if ($course->isFree())
                                    <span class="status status-on">Free</span>
                                @elseif ($course->plans->isNotEmpty())
                                    {{ $course->formattedPrice() }}
                                    @if ($course->bestDiscount())<b class="disc-pill">−{{ $course->bestDiscount() }}%</b>@endif
                                    <span class="row-sub">{{ $course->plans->count() }} {{ \Illuminate\Support\Str::plural('plan', $course->plans->count()) }}@if ($course->duration) · {{ $course->duration }}@endif</span>
                                @else
                                    <span class="muted">not sold online</span>
                                @endif
                            </td>
                            <td class="num">{{ $course->modules_count }} mod · {{ $course->lessons_count }} lessons · {{ $course->quizzes_count }} tests</td>
                            <td class="num">{{ $course->enrollments_count }}</td>
                            <td><span class="status {{ $course->is_published ? 'status-on' : 'status-off' }}">{{ $course->is_published ? 'Live' : 'Hidden' }}</span></td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm btn-icon" href="{{ $course->url() }}" target="_blank" rel="noopener" title="View on website" aria-label="View on website">@include('admin.partials.icon', ['name' => 'eye'])</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.courses.curriculum', $course) }}">@include('admin.partials.icon', ['name' => 'grid']) Curriculum</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.courses.edit', $course) }}">@include('admin.partials.icon', ['name' => 'edit']) Edit</a>
                                <form method="post" action="{{ route('admin.courses.duplicate', $course) }}">
                                    @csrf
                                    <button class="btn btn-ghost btn-sm btn-icon" title="Duplicate (copy as a hidden draft)" aria-label="Duplicate">@include('admin.partials.icon', ['name' => 'pages'])</button>
                                </form>
                                <form method="post" action="{{ route('admin.courses.destroy', $course) }}" data-confirm="Delete the course “{{ $course->title }}”? This cannot be undone.">
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
