{{-- Take one student out of the class (their course access and progress stay). --}}
<form method="post" action="{{ route('admin.classes.students.destroy', [$classroom, $student]) }}"
      data-confirm="Take {{ $student->name }} out of this class? Their course access and progress stay as they are.">
    @csrf @method('DELETE')
    <button class="btn btn-ghost btn-sm btn-icon" title="Take out of the class" aria-label="Take {{ $student->name }} out of the class">@include('admin.partials.icon', ['name' => 'trash'])</button>
</form>
