@extends('admin.layout')

@section('title', $testimonial->exists ? $testimonial->name : 'New testimonial')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.testimonials.index') }}">Testimonials</a>
    <span aria-hidden="true">/</span><span>{{ $testimonial->exists ? 'Edit' : 'New' }}</span>
@endsection
@section('actions')
    @if ($testimonial->exists)
        <form method="post" action="{{ route('admin.testimonials.destroy', $testimonial) }}" data-confirm="Delete the testimonial from {{ $testimonial->name }}?">
            @csrf @method('DELETE')
            <button class="btn btn-danger">@include('admin.partials.icon', ['name' => 'trash']) Delete</button>
        </form>
    @endif
@endsection

@section('content')
    <form method="post" enctype="multipart/form-data" data-main-form
          action="{{ $testimonial->exists ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}">
        @csrf
        @if ($testimonial->exists) @method('PUT') @endif

        <div class="form-grid reveal">
            <section class="card">
                <div class="card-head"><div><h2>Testimonial</h2><p>Who said it and what they said.</p></div></div>
                <div class="card-body fields">
                    <div class="cols-2">
                        @include('admin.fields.input', ['name' => 'name', 'label' => 'Name', 'value' => $testimonial->name, 'required' => true])
                        @include('admin.fields.input', ['name' => 'role', 'label' => 'Role or course', 'value' => $testimonial->role, 'placeholder' => 'Advanced Excel learner'])
                    </div>
                    @include('admin.fields.textarea', ['name' => 'quote', 'label' => 'Quote', 'value' => $testimonial->quote, 'rows' => 8, 'required' => true, 'max' => 1000])
                    <div class="field">
                        <label class="field-label" for="f-rating"><span>Rating</span></label>
                        <select id="f-rating" name="rating">
                            @for ($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}" @selected(old('rating', $testimonial->rating) == $i)>{{ str_repeat('★', $i) }}{{ str_repeat('☆', 5 - $i) }} ({{ $i }}/5)</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </section>

            <div class="form-side">
                @include('admin.fields.publish', ['published' => $testimonial->is_published, 'sort' => $testimonial->sort_order ?? 0, 'model' => $testimonial])
                <section class="card">
                    <div class="card-head"><h2>Photo</h2></div>
                    <div class="card-body">
                        @include('admin.fields.image', ['name' => 'photo', 'remove' => 'remove_photo', 'label' => 'Photo', 'value' => $testimonial->photo, 'help' => 'A square photo works best.'])
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
