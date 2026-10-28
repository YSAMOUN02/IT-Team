@extends('admin.layout')

@section('title', $course->exists ? $course->title : 'New course')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.courses.index') }}">Courses</a>
    <span aria-hidden="true">/</span><span>{{ $course->exists ? 'Edit' : 'New' }}</span>
@endsection
@section('actions')
    @if ($course->exists)
        <form method="post" action="{{ route('admin.courses.destroy', $course) }}" data-confirm="Delete the course “{{ $course->title }}” with all its lessons, quizzes and student progress? This cannot be undone.">
            @csrf @method('DELETE')
            <button class="btn btn-danger">@include('admin.partials.icon', ['name' => 'trash']) Delete</button>
        </form>
    @endif
@endsection

@section('content')
    @if ($course->exists)
        @include('admin.courses._workspace', ['course' => $course, 'tab' => 'details'])
    @else
        <ol class="steps-bar">
            <li class="on"><b>1</b> Details &amp; price</li>
            <li><b>2</b> Modules, lessons &amp; tests</li>
            <li><b>3</b> Preview as a student &amp; publish</li>
        </ol>
    @endif

    <form method="post" enctype="multipart/form-data" data-main-form
          action="{{ $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store') }}">
        @csrf
        @if ($course->exists) @method('PUT') @else <input type="hidden" name="to_curriculum" value="1"> @endif

        <div class="form-grid reveal">
            <div>
                <section class="card">
                    <div class="card-head"><div><h2>Course</h2><p>Name, summary and the full description.</p></div></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'title', 'label' => 'Title', 'value' => $course->title, 'required' => true, 'attrs' => 'data-slug-source'])
                        @include('admin.fields.input', ['name' => 'slug', 'label' => 'Web address', 'value' => $course->slug, 'prefix' => '/courses/', 'attrs' => 'data-slug', 'help' => 'Leave empty to make it from the title.'])
                        @include('admin.fields.textarea', ['name' => 'summary', 'label' => 'Short summary', 'value' => $course->summary, 'rows' => 3, 'max' => 300, 'help' => 'Shown on course cards and under the title.'])
                        @include('admin.fields.input', ['name' => 'duration', 'label' => 'Course duration', 'value' => $course->duration, 'placeholder' => 'e.g. 12 hours · 6 weeks · ១២ ម៉ោង', 'help' => 'How long the course takes. Shown on the course card and page.'])
                        @include('admin.fields.textarea', ['name' => 'features', 'label' => 'What the course covers', 'value' => implode("\n", $course->features ?? []), 'rows' => 10, 'help' => 'One item per line. To make groups: a heading line ending with ៖ (or starting with ✅), then its items on lines starting with "- ".'])
                        @include('admin.fields.textarea', ['name' => 'body', 'label' => 'Full description', 'value' => $course->body, 'editor' => true])
                    </div>
                </section>

                @include('admin.courses._plans', ['course' => $course])
            </div>

            <div class="form-side">
                @include('admin.fields.publish', ['published' => $course->exists ? $course->is_published : false, 'sort' => $course->sort_order ?? 0, 'viewUrl' => $course->exists ? $course->url() : null, 'model' => $course, 'saveLabel' => $course->exists ? null : 'Save & build curriculum →'])
                <section class="card">
                    <div class="card-head"><div><h2>Course images</h2><p>The cover shows on course cards, the course page and when the link is shared. For pictures inside the text, use the description editor.</p></div></div>
                    <div class="card-body fields">
                        @include('admin.fields.image', ['name' => 'image', 'remove' => 'remove_image', 'label' => 'Cover image', 'value' => $course->image, 'help' => 'Wide picture, about 1200 × 675 (16:9). JPG, PNG or WebP.'])
                        @include('admin.fields.image', ['name' => 'icon', 'remove' => 'remove_icon', 'label' => 'Card icon', 'value' => $course->icon, 'help' => 'Small square icon, e.g. 80 × 80.'])
                    </div>
                </section>
                <section class="card">
                    <div class="card-head"><div><h2>Completion</h2></div></div>
                    <div class="card-body fields">
                        <label class="switch">
                            <span class="switch-text"><strong>Certificate</strong><span>Give a certificate when the course is completed.</span></span>
                            <input type="checkbox" name="certificate_enabled" value="1" @checked(old('certificate_enabled', $course->certificate_enabled ?? true))>
                            <span class="switch-ui" aria-hidden="true"></span>
                        </label>
                        @if ($course->exists)
                            <a class="btn btn-ghost btn-block" href="{{ route('admin.courses.curriculum', $course) }}">@include('admin.partials.icon', ['name' => 'grid']) Modules, lessons &amp; exams</a>
                        @endif
                    </div>
                </section>
                <section class="card">
                    <div class="card-head"><div><h2>Old price text</h2><p>Only shown when the course has no price plans and is not free.</p></div></div>
                    <div class="card-body fields">
                        <div class="cols-2">
                            @include('admin.fields.input', ['name' => 'price_prefix', 'label' => 'Above price', 'value' => $course->price_prefix, 'placeholder' => 'Starting at'])
                            @include('admin.fields.input', ['name' => 'price', 'label' => 'Price', 'value' => $course->price, 'placeholder' => '$49'])
                        </div>
                        <div class="cols-2">
                            @include('admin.fields.input', ['name' => 'price_suffix', 'label' => 'Below price', 'value' => $course->price_suffix, 'placeholder' => 'Per year'])
                            @include('admin.fields.input', ['name' => 'badge', 'label' => 'Learners', 'value' => $course->badge, 'placeholder' => '150'])
                        </div>
                        @include('admin.fields.input', ['name' => 'cta_label', 'label' => 'Button text', 'value' => $course->cta_label])
                        @include('admin.fields.input', ['name' => 'cta_url', 'label' => 'Button link', 'value' => $course->cta_url, 'placeholder' => '/contact', 'help' => 'Empty = jump to the form on the course page.'])
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
