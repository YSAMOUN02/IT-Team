{{-- "Class" filter for a toolbar form: $filterClasses (Classroom list), $filterClass (chosen id or null). The form submits on change. --}}
@if ($filterClasses->isNotEmpty())
    <select name="class" aria-label="Class" onchange="this.form.submit()">
        <option value="">All classes</option>
        @foreach ($filterClasses as $filterOption)
            <option value="{{ $filterOption->id }}" @selected((int) $filterClass === $filterOption->id)>Class: {{ $filterOption->name }}</option>
        @endforeach
    </select>
@endif
