{{-- A student's classes as small links: $chipClasses (a collection of Classroom, may be empty). --}}
@if (isset($chipClasses) && $chipClasses->isNotEmpty())
    <span class="class-chips">
        @foreach ($chipClasses as $chipClass)
            <a class="class-chip" href="{{ route('admin.classes.show', $chipClass) }}" title="Class">@include('admin.partials.icon', ['name' => 'grid']) {{ $chipClass->name }}</a>
        @endforeach
    </span>
@endif
