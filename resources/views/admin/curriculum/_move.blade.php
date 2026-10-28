<form method="post" action="{{ route('admin.curriculum.move') }}" class="move-btns">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="id" value="{{ $id }}">
    <button class="btn btn-ghost btn-sm btn-icon" name="direction" value="up" title="Move up" aria-label="Move up">↑</button>
    <button class="btn btn-ghost btn-sm btn-icon" name="direction" value="down" title="Move down" aria-label="Move down">↓</button>
</form>
