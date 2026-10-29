{{-- Under the access status: turn this course off / on again (progress and dates are kept), or remove the access. --}}
<div class="row-sub">
    <form method="post" action="{{ route('admin.enrollments.update', $enrollment) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="disabled" value="{{ $enrollment->disabled_at ? 0 : 1 }}">
        <button class="link-btn">{{ $enrollment->disabled_at ? 'Turn on' : 'Turn off' }}</button>
    </form> ·
    <form method="post" action="{{ route('admin.enrollments.destroy', $enrollment) }}"
          data-confirm="Remove {{ $enrollment->user->name }}'s access to {{ $enrollment->course->title }}? Their progress is kept if you give access again.{{ $enrollment->certificate_code ? ' Their certificate '.$enrollment->certificate_code.' stops working.' : '' }}">
        @csrf @method('DELETE')
        <button class="link-btn link-danger">Remove</button>
    </form>
</div>
