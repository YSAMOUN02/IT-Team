@extends('admin.layout')

@section('title', $lesson->exists ? $lesson->title : 'New lesson')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.courses.index') }}">Courses</a>
    <span aria-hidden="true">/</span><a href="{{ route('admin.courses.curriculum', $course) }}">{{ \Illuminate\Support\Str::limit($course->title, 30) }}</a>
    <span aria-hidden="true">/</span><span>{{ $lesson->exists ? 'Lesson' : 'New lesson' }}</span>
@endsection
@section('actions')
    <a class="btn btn-ghost" href="{{ route('admin.courses.curriculum', $course) }}">← Curriculum</a>
    @if ($lesson->exists)
        <a class="btn btn-ghost" href="{{ route('learn.lesson', [$course->slug, $lesson->slug]) }}" target="_blank" rel="noopener">@include('admin.partials.icon', ['name' => 'user']) View as student</a>
        <form method="post" action="{{ route('admin.lessons.destroy', $lesson) }}" data-confirm="Delete the lesson “{{ $lesson->title }}” and its files?">
            @csrf @method('DELETE')
            <button class="btn btn-danger">@include('admin.partials.icon', ['name' => 'trash']) Delete</button>
        </form>
    @endif
@endsection

@section('content')
    <form method="post" enctype="multipart/form-data" data-main-form
          action="{{ $lesson->exists ? route('admin.lessons.update', $lesson) : route('admin.courses.lessons.store', $course) }}">
        @csrf
        @if ($lesson->exists) @method('PUT') @endif

        <div class="form-grid reveal">
            <div>
                <section class="card">
                    <div class="card-head"><div><h2>Lesson</h2><p>What students watch and read.</p></div></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'title', 'label' => 'Title', 'value' => $lesson->title, 'required' => true, 'attrs' => 'data-slug-source'])
                        <div class="cols-2">
                            <div class="field">
                                <label class="field-label" for="f-module"><span>Module</span></label>
                                <select id="f-module" name="course_module_id">
                                    <option value="">— Not in a module —</option>
                                    @foreach ($course->modules as $module)
                                        <option value="{{ $module->id }}" @selected(old('course_module_id', $lesson->course_module_id) == $module->id)>{{ $loop->iteration }}. {{ $module->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @include('admin.fields.input', ['name' => 'duration', 'label' => 'Length', 'value' => $lesson->duration, 'placeholder' => '12:30'])
                        </div>
                        @include('admin.fields.input', ['name' => 'slug', 'label' => 'Web address', 'value' => $lesson->slug, 'prefix' => '/learn/'.$course->slug.'/lessons/', 'attrs' => 'data-slug'])
                    </div>
                </section>

                <section class="card">
                    <div class="card-head"><div><h2>Video</h2><p>A YouTube / Vimeo link, or upload an MP4. Use “unlisted” on YouTube so only students find it.</p></div></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'video_url', 'label' => 'YouTube or Vimeo link', 'type' => 'url', 'value' => $lesson->video_url, 'placeholder' => 'https://www.youtube.com/watch?v=…'])
                        <div class="field">
                            <span class="field-label"><span>…or upload a video file (MP4)</span></span>
                            @if ($lesson->video_path)
                                <div class="file-current">
                                    @include('admin.partials.icon', ['name' => 'tutorials'])
                                    <span class="mono">{{ basename($lesson->video_path) }}</span>
                                    <label class="check"><input type="checkbox" name="remove_video" value="1"> Remove</label>
                                </div>
                            @endif
                            <input type="file" name="video" accept="video/mp4,video/webm,video/quicktime">
                            <span class="help">Uploaded videos play only for students with access. Large files need a matching PHP upload limit.</span>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <div class="card-head"><div><h2>Lesson notes</h2><p>Explanations, steps, screenshots, formulas.</p></div></div>
                    <div class="card-body">
                        @include('admin.fields.textarea', ['name' => 'body', 'label' => 'Notes', 'value' => $lesson->body, 'editor' => true])
                    </div>
                </section>
            </div>

            <div class="form-side">
                <section class="card">
                    <div class="card-head"><h2>Publish</h2></div>
                    <div class="card-body fields">
                        <label class="switch">
                            <span class="switch-text"><strong>Visible to students</strong><span>Off = hidden draft.</span></span>
                            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $lesson->is_published ?? true))>
                            <span class="switch-ui" aria-hidden="true"></span>
                        </label>
                        <label class="switch">
                            <span class="switch-text"><strong>Free preview</strong><span>Anyone can watch it before buying.</span></span>
                            <input type="checkbox" name="is_preview" value="1" @checked(old('is_preview', $lesson->is_preview))>
                            <span class="switch-ui" aria-hidden="true"></span>
                        </label>
                    </div>
                    <div class="card-foot save-bar">
                        <button type="submit" class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Save lesson</button>
                    </div>
                </section>

                <section class="card">
                    <div class="card-head"><div><h2>Practice files</h2><p>Excel workbook to practise with, and its answers.</p></div></div>
                    <div class="card-body fields">
                        @foreach (['practice' => ['Practice workbook', 'attachment_path', 'attachment_name'], 'solution' => ['Solution', 'solution_path', 'solution_name']] as $input => [$label, $pathCol, $nameCol])
                            <div class="field">
                                <span class="field-label"><span>{{ $label }}</span></span>
                                @if ($lesson->{$pathCol})
                                    <div class="file-current">
                                        <span class="xl-ico">XLS</span>
                                        <span class="mono">{{ $lesson->{$nameCol} }}</span>
                                        <label class="check"><input type="checkbox" name="remove_{{ $input }}" value="1"> Remove</label>
                                    </div>
                                @endif
                                <input type="file" name="{{ $input }}" accept=".xlsx,.xls,.xlsm,.csv,.pdf,.docx,.pptx,.zip">
                            </div>
                        @endforeach
                        <span class="help">Excel, CSV, PDF, Word, PowerPoint or ZIP, up to 50 MB. The solution is shown only to students who bought the course.</span>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
