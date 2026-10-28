@extends('layouts.site')

@section('title', $page->title)
@section('admin_edit', route('admin.pages.edit', $page))
@section('description', $page->meta_description ?: strip_tags((string) $page->field('intro.text')))

@section('content')
    @include('partials.page-header', [
        'title' => $page->field('intro.title', $page->title),
        'text' => $page->field('intro.text'),
        'buttons' => [
            [$page->field('intro.button_label'), $page->field('intro.button_url')],
            [$page->field('intro.button2_label'), $page->field('intro.button2_url')],
        ],
    ])

    @if ($page->field('who.title'))
        @include('pages._who')
    @endif

    @foreach (['mission' => true] as $key => $reverse)
        @if ($page->field("$key.title"))
            <section @class(['section', 'section-tint' => $reverse])>
                <div @class(['container', 'split', 'split-reverse' => ! $reverse])>
                    <div class="split-copy">
                        <h2>{{ $page->field("$key.title") }}</h2>
                        <div class="prose">{!! rich_text($page->field("$key.text")) !!}</div>
                    </div>
                    @if ($page->field("$key.image"))
                        <div @class(['split-media', 'split-media-plain' => $reverse])>
                            <img src="{{ media_url($page->field("$key.image")) }}" alt="" loading="lazy">
                        </div>
                    @endif
                </div>
            </section>
        @endif
    @endforeach

    @if ($page->field('benefits.title'))
        <section class="section">
            <div class="container narrow benefits">
                <h2>{{ $page->field('benefits.title') }}</h2>
                @include('partials.checklist', ['items' => $page->field('benefits.points', [])])
            </div>
        </section>
    @endif
@endsection
