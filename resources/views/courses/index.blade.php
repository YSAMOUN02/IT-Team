@extends('layouts.site')

@section('title', $page?->title ?? __('site.courses'))
@section('description', $page?->meta_description ?: strip_tags((string) $page?->field('intro.text')))

@section('content')
    @include('partials.page-header', [
        'title' => $page?->field('intro.title') ?? __('site.our_courses'),
        'text' => $page?->field('intro.text'),
    ])

    <section class="section">
        <div class="container">
            @if ($courses->isEmpty())
                <p class="empty">{{ __('site.no_items') }}</p>
            @else
                <div class="grid grid-3">
                    @foreach ($courses as $course)
                        @include('partials.course-card')
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
