{{-- Shown to signed-in admins on public pages: which view this is, how to switch, and where to edit it. --}}
@php
    $route = request()->route();
    $courseParam = $route?->parameter('course');
    $courseSlug = $courseParam instanceof \App\Models\Course ? $courseParam->slug : $courseParam;
    $asVisitor = request()->routeIs('courses.show') && request()->query('as') === 'visitor';
    $mode = match (true) {
        request()->routeIs('learn.*') => 'student',
        $asVisitor => 'visitor',
        default => 'admin',
    };
    $editUrl = trim($__env->yieldContent('admin_edit'));
@endphp
<div class="admin-bar" role="region" aria-label="Admin preview">
    <div class="container admin-bar-inner">
        <a class="admin-bar-brand" href="{{ route('admin.dashboard') }}">⚙ Admin</a>
        <span class="admin-bar-mode">
            @if ($mode === 'student')
                👁 Viewing as a <b>student</b> who bought this course
            @elseif ($mode === 'visitor')
                👁 Viewing as a <b>visitor</b> (not bought)
            @else
                You are signed in as admin
            @endif
        </span>
        @if ($courseSlug)
            <span class="admin-bar-switch">
                <a href="{{ route('courses.show', ['course' => $courseSlug, 'as' => 'visitor']) }}" @class(['on' => $mode === 'visitor'])>Visitor</a>
                <a href="{{ route('learn.course', $courseSlug) }}" @class(['on' => $mode === 'student'])>Student</a>
            </span>
        @endif
        @if ($editUrl)
            <a class="admin-bar-edit" href="{{ $editUrl }}">✎ Edit this page</a>
        @endif
    </div>
</div>
