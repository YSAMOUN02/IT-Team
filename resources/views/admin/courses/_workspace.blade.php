{{-- Course workspace header: tabs, preview links and the setup checklist. $course, $tab = details|curriculum|students --}}
@php
    $setup = \App\Support\CourseSetup::for($course);
    $missing = count(array_filter($setup->steps, fn ($s) => ! $s['done'] && ! $s['optional']));
@endphp
<section class="workspace">
    <div class="workspace-top">
        <div class="workspace-title">
            @if ($course->image || $course->icon)
                <img src="{{ $course->mediaUrl() ?? $course->mediaUrl('icon') }}" alt="">
            @endif
            <div>
                <span class="status {{ $course->is_published ? 'status-on' : 'status-off' }}">{{ $course->is_published ? 'Live on website' : 'Hidden draft' }}</span>
                <strong>{{ $course->title }}</strong>
            </div>
        </div>
        <div class="workspace-actions">
            <a class="btn btn-ghost btn-sm" href="{{ route('courses.show', ['course' => $course->slug, 'as' => 'visitor']) }}" target="_blank" rel="noopener" title="The public course page as someone who has not bought it">@include('admin.partials.icon', ['name' => 'eye']) View as visitor</a>
            <a class="btn btn-ghost btn-sm" href="{{ route('learn.course', $course->slug) }}" target="_blank" rel="noopener" title="The course player as a student who bought it">@include('admin.partials.icon', ['name' => 'user']) View as student</a>
            <form method="post" action="{{ route('admin.courses.duplicate', $course) }}">
                @csrf
                <button class="btn btn-ghost btn-sm" title="Copy the course and its curriculum as a hidden draft">@include('admin.partials.icon', ['name' => 'pages']) Duplicate</button>
            </form>
        </div>
    </div>

    <nav class="workspace-tabs" aria-label="Course sections">
        <a href="{{ route('admin.courses.edit', $course) }}" @class(['on' => $tab === 'details'])>@include('admin.partials.icon', ['name' => 'edit']) Details &amp; price</a>
        <a href="{{ route('admin.courses.curriculum', $course) }}" @class(['on' => $tab === 'curriculum'])>@include('admin.partials.icon', ['name' => 'grid']) Curriculum <span class="mono muted">{{ $course->lessons()->count() + $course->quizzes()->count() }}</span></a>
        <a href="{{ route('admin.courses.students', $course) }}" @class(['on' => $tab === 'students'])>@include('admin.partials.icon', ['name' => 'users']) Students <span class="mono muted">{{ $course->enrollments()->count() }}</span></a>

        <details class="setup" @if ($setup->percent < 100 && $tab === 'curriculum') open @endif>
            <summary>
                <span class="setup-meter" style="--p: {{ $setup->percent }}"><span>{{ $setup->percent }}%</span></span>
                {{ $setup->percent === 100 ? 'Ready to sell' : $missing.' step'.($missing === 1 ? '' : 's').' left' }}
            </summary>
            <ol class="setup-list">
                @foreach ($setup->steps as $step)
                    <li @class(['done' => $step['done'], 'optional' => $step['optional']])>
                        <span class="setup-tick" aria-hidden="true">{{ $step['done'] ? '✓' : ($step['optional'] ? '○' : '!') }}</span>
                        <div>
                            @if ($step['done'])
                                <strong>{{ $step['label'] }}</strong>
                            @else
                                <a href="{{ $step['url'] }}"><strong>{{ $step['label'] }}</strong></a>
                            @endif
                            @if ($step['optional'])<em>recommended</em>@endif
                            <small>{{ $step['hint'] }}</small>
                        </div>
                    </li>
                @endforeach
            </ol>
        </details>
    </nav>
</section>
